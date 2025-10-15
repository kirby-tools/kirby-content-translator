import { useApi } from "kirbyuse";
import pAll from "p-all";
import * as yaml from "yaml";
import {
  TRANSLATE_API_ROUTE,
  TRANSLATE_BATCH_API_ROUTE,
  TRANSLATE_KIRBYTEXT_API_ROUTE,
} from "../constants";
import { flattenTabFields, isBlockTranslatable, isObject } from "./shared";

/**
 * Translates content recursively, handling various field types including blocks, layouts, structures, etc.
 *
 * @param {object} obj - The content object to translate
 * @param {object} options - Translation options
 * @param {string} options.sourceLanguage - Source language code
 * @param {string} options.targetLanguage - Target language code
 * @param {Array<string>} options.fieldTypes - Array of field types to translate
 * @param {Array<string>} options.includeFields - Array of field names to include
 * @param {Array<string>} options.excludeFields - Array of field names to exclude
 * @param {object} options.kirbyTags - KirbyText tags configuration
 * @param {object} options.fields - Field definitions
 * @returns {Promise<object>} The translated content object
 */
export async function translateContent(
  obj,
  {
    sourceLanguage,
    targetLanguage,
    fieldTypes,
    includeFields = [],
    excludeFields = [],
    kirbyTags,
    fields,
  },
) {
  const api = useApi();
  const tasks = [];
  const finalizationTasks = [];

  // Batch translation collector for text-like fields
  const batchCollector = {
    texts: [],
    callbacks: [],
  };

  function collectTranslatableFields(obj, fields) {
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

      // Handle text-like fields (collect for batch translation)
      if (["list", "text", "writer"].includes(fields[key].type)) {
        batchCollector.texts.push(obj[key]);
        batchCollector.callbacks.push((translatedText) => {
          obj[key] = translatedText;
        });
      }

      // Handle markdown content separately
      else if (["textarea", "markdown"].includes(fields[key].type)) {
        // Skip empty content
        if (!obj[key]?.trim()) continue;

        tasks.push(async () => {
          const response = await api.post(TRANSLATE_KIRBYTEXT_API_ROUTE, {
            sourceLanguage,
            targetLanguage,
            text: obj[key],
            kirbyTags,
          });
          obj[key] = response.text;
        });
      }

      // Handle tags fields (collect for batch translation)
      else if (
        fields[key].type === "tags" &&
        Array.isArray(obj[key]) &&
        obj[key].length
      ) {
        // Join tags with separator for translation
        const text = obj[key].join(" | ");
        batchCollector.texts.push(text);
        batchCollector.callbacks.push((translatedText) => {
          // Split translated text back into array
          obj[key] = translatedText.split("|").map((tag) => tag.trim());
        });
      }

      // Handle table fields (https://github.com/bogdancondorachi/kirby-table-field)
      else if (fields[key].type === "table") {
        let tableData = obj[key];
        let isYamlEncoded = false;

        // Panel content data is not deserialized, so we need to parse it first
        if (typeof tableData === "string") {
          isYamlEncoded = true;
          try {
            tableData = yaml.parse(tableData);
          } catch (error) {
            console.error(
              `Failed to parse table field "${key}" as YAML:`,
              error,
            );
            continue;
          }
        }

        if (Array.isArray(tableData)) {
          // Translate each cell in the table
          for (const [rowIndex, row] of tableData.entries()) {
            if (!Array.isArray(row)) continue;

            for (const [colIndex, cell] of row.entries()) {
              // Skip empty cells
              if (!cell || typeof cell !== "string" || !cell.trim()) continue;

              tasks.push(async () => {
                const response = await api.post(TRANSLATE_API_ROUTE, {
                  sourceLanguage,
                  targetLanguage,
                  text: cell,
                });
                tableData[rowIndex][colIndex] = response.text;
              });
            }
          }

          obj[key] = tableData;

          // Serialize back to YAML after all translations are done
          if (isYamlEncoded) {
            finalizationTasks.push(async () => {
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

              obj[key] = rowStrings
                .map((rowString) => `-\n${rowString}`)
                .join("\n");
            });
          }
        }
      }

      // Handle structure fields
      else if (fields[key].type === "structure" && Array.isArray(obj[key])) {
        // Recursively translate each structure item
        for (const item of obj[key]) {
          collectTranslatableFields(item, fields[key].fields);
        }
      }

      // Handle object fields
      else if (fields[key].type === "object" && isObject(obj[key])) {
        // Recursively translate object content
        collectTranslatableFields(obj[key], fields[key].fields);
      }

      // Handle layout fields
      else if (fields[key].type === "layout" && Array.isArray(obj[key])) {
        for (const layout of obj[key]) {
          for (const column of layout.columns) {
            for (const block of column.blocks) {
              // Skip non-translatable blocks
              if (!isBlockTranslatable(block)) continue;
              // Skip if block type definition doesn't exist
              if (!fields[key].fieldsets[block.type]) continue;

              const blockFields = flattenTabFields(
                fields[key].fieldsets,
                block,
              );
              // Recursively translate block content
              collectTranslatableFields(block.content, blockFields);
            }
          }
        }
      }

      // Handle block fields
      else if (fields[key].type === "blocks" && Array.isArray(obj[key])) {
        for (const block of obj[key]) {
          // Skip non-translatable blocks
          if (!isBlockTranslatable(block)) continue;
          // Skip if block type definition doesn't exist
          if (!fields[key].fieldsets[block.type]) continue;

          const blockFields = flattenTabFields(fields[key].fieldsets, block);
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
      const response = await api.post(TRANSLATE_BATCH_API_ROUTE, {
        sourceLanguage,
        targetLanguage,
        texts: batchCollector.texts,
      });

      for (const [index, translatedText] of response.texts.entries()) {
        batchCollector.callbacks[index](translatedText);
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
