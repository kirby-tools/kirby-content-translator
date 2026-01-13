import type {
  KirbyBlock,
  KirbyBlocksFieldProps,
  KirbyFieldProps,
  KirbyLayout,
  KirbyLayoutFieldProps,
  KirbyObjectFieldProps,
  KirbyStructureFieldProps,
} from "kirby-types";
import { useApi } from "kirbyuse";
import pAll from "p-all";
import * as yaml from "yaml";
import {
  TRANSLATE_API_ROUTE,
  TRANSLATE_BATCH_API_ROUTE,
  TRANSLATE_KIRBYTEXT_API_ROUTE,
} from "../constants";
import { flattenTabFields, isBlockTranslatable } from "./fields";
import { isObject } from "./shared";

type TranslationTask = () => Promise<void>;

/**
 * Translates content recursively, handling various field types including blocks, layouts, structures, etc.
 */
export async function translateContent(
  obj: Record<string, unknown>,
  {
    sourceLanguage,
    targetLanguage,
    fieldTypes,
    includeFields = [],
    excludeFields = [],
    kirbyTags,
    fields,
  }: {
    sourceLanguage?: string;
    targetLanguage: string;
    fieldTypes: string[] | readonly string[];
    includeFields?: string[];
    excludeFields?: string[];
    kirbyTags: Record<string, unknown>;
    fields: Record<string, KirbyFieldProps>;
  },
) {
  const api = useApi();
  const tasks: TranslationTask[] = [];
  const finalizationTasks: TranslationTask[] = [];

  // Batch translation collector for text-like fields
  const batchCollector = {
    texts: [] as string[],
    callbacks: [] as ((translatedText: string) => void)[],
  };

  function collectTranslatableFields(
    obj: Record<string, unknown>,
    fields: Record<string, KirbyFieldProps>,
  ) {
    for (const key in obj) {
      // Skip empty values
      if (!obj[key]) continue;
      // Skip if field definition doesn't exist
      if (!fields[key]) continue;
      // Skip if field has translate = false
      if (fields[key].translate === false) continue;
      // Skip if field type is not in the translatable types list
      if (!fieldTypes.includes(fields[key].type)) continue;

      // Apply include/exclude filters
      if (includeFields.length && !includeFields.includes(key)) continue;
      if (excludeFields.length && excludeFields.includes(key)) continue;

      const field = fields[key];

      // Handle text-like fields (collect for batch translation)
      if (["list", "text", "writer"].includes(field.type)) {
        batchCollector.texts.push(obj[key] as string);
        batchCollector.callbacks.push((translatedText: string) => {
          obj[key] = translatedText;
        });
      }

      // Handle markdown content separately
      else if (["textarea", "markdown"].includes(field.type)) {
        const text = obj[key] as string;
        // Skip empty content
        if (!text?.trim()) continue;

        tasks.push(async () => {
          const response = await api.post<{ text: string }>(
            TRANSLATE_KIRBYTEXT_API_ROUTE,
            {
              sourceLanguage,
              targetLanguage,
              text,
              kirbyTags,
            },
          );
          obj[key] = response.text;
        });
      }

      // Handle tags fields (collect for batch translation)
      else if (field.type === "tags") {
        const tags = obj[key];
        if (!Array.isArray(tags) || !tags.length) continue;

        // Join tags with separator for translation
        const text = (tags as string[]).join(" | ");
        batchCollector.texts.push(text);
        batchCollector.callbacks.push((translatedText: string) => {
          // Split translated text back into array
          obj[key] = translatedText.split("|").map((tag) => tag.trim());
        });
      }

      // Handle table fields (https://github.com/bogdancondorachi/kirby-table-field)
      else if (field.type === "table") {
        collectTableTranslations(obj, key, {
          sourceLanguage,
          targetLanguage,
          tasks,
          finalizationTasks,
        });
      }

      // Handle structure fields
      else if (field.type === "structure" && Array.isArray(obj[key])) {
        const structureField = field as KirbyStructureFieldProps;
        // Recursively translate each structure item
        for (const item of obj[key]) {
          collectTranslatableFields(item, structureField.fields);
        }
      }

      // Handle object fields
      else if (field.type === "object" && isObject(obj[key])) {
        const objectField = field as KirbyObjectFieldProps;
        // Recursively translate object content
        collectTranslatableFields(obj[key], objectField.fields);
      }

      // Handle layout fields
      else if (field.type === "layout" && Array.isArray(obj[key])) {
        const layoutField = field as KirbyLayoutFieldProps;
        for (const layout of obj[key] as KirbyLayout[]) {
          for (const column of layout.columns) {
            for (const block of column.blocks) {
              // Skip non-translatable blocks
              if (!isBlockTranslatable(block)) continue;
              // Skip if block type definition doesn't exist
              if (!layoutField.fieldsets[block.type]) continue;

              const blockFields = flattenTabFields(
                layoutField.fieldsets,
                block,
              );
              // Recursively translate block content
              collectTranslatableFields(block.content, blockFields);
            }
          }
        }
      }

      // Handle block fields
      else if (field.type === "blocks" && Array.isArray(obj[key])) {
        const blocksField = field as KirbyBlocksFieldProps;
        for (const block of obj[key] as KirbyBlock[]) {
          // Skip non-translatable blocks
          if (!isBlockTranslatable(block)) continue;
          // Skip if block type definition doesn't exist
          if (!blocksField.fieldsets[block.type]) continue;

          const blockFields = flattenTabFields(blocksField.fieldsets, block);
          // Recursively translate block content
          collectTranslatableFields(block.content, blockFields);
        }
      }
    }
  }

  collectTranslatableFields(obj, fields);

  // Add batch translation task if there are texts to translate
  if (batchCollector.texts.length > 0) {
    tasks.unshift(async () => {
      const response = await api.post<{ texts: string[] }>(
        TRANSLATE_BATCH_API_ROUTE,
        {
          sourceLanguage,
          targetLanguage,
          texts: batchCollector.texts,
        },
      );

      for (const [index, translatedText] of response.texts.entries()) {
        batchCollector.callbacks[index]!(translatedText);
      }
    });
  }

  // Process translation tasks in batches
  try {
    await pAll(tasks, { concurrency: 5 });
    await pAll(finalizationTasks, { concurrency: 5 });
  } catch (error) {
    console.error(error);
    throw error;
  }

  return obj;
}

function collectTableTranslations(
  obj: Record<string, unknown>,
  key: string,
  ctx: {
    sourceLanguage?: string;
    targetLanguage: string;
    tasks: TranslationTask[];
    finalizationTasks: TranslationTask[];
  },
) {
  const api = useApi();

  let tableData = obj[key] as string | string[][];
  let isYamlEncoded = false;

  // Panel content data is not deserialized, so we need to parse it first
  if (typeof tableData === "string") {
    isYamlEncoded = true;
    try {
      tableData = yaml.parse(tableData) as string[][];
    } catch (error) {
      console.error(`Failed to parse table field "${key}" as YAML:`, error);
      return;
    }
  }

  if (!Array.isArray(tableData)) return;

  // Translate each cell in the table
  for (const [rowIndex, row] of tableData.entries()) {
    if (!Array.isArray(row)) continue;

    for (const [colIndex, cell] of row.entries()) {
      // Skip empty cells
      if (!cell || typeof cell !== "string" || !cell.trim()) continue;

      ctx.tasks.push(async () => {
        const response = await api.post<{ text: string }>(TRANSLATE_API_ROUTE, {
          sourceLanguage: ctx.sourceLanguage,
          targetLanguage: ctx.targetLanguage,
          text: cell,
        });
        tableData[rowIndex]![colIndex] = response.text;
      });
    }
  }

  obj[key] = tableData;

  // Serialize back to YAML after all translations are done
  if (isYamlEncoded) {
    ctx.finalizationTasks.push(async () => {
      // Stringify each row individually to match the input format
      const rowStrings = tableData.map((row) => {
        if (!Array.isArray(row)) return "";
        return yaml
          .stringify(row)
          .trim()
          .split("\n")
          .map((line) => `  ${line}`)
          .join("\n");
      });

      obj[key] = rowStrings.map((rowString) => `-\n${rowString}`).join("\n");
    });
  }
}
