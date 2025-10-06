import { useApi } from "kirbyuse";
import pAll from "p-all";
import * as yaml from "yaml";
import {
  TRANSLATE_API_ROUTE,
  TRANSLATE_BATCH_API_ROUTE,
  TRANSLATE_KIRBYTEXT_API_ROUTE,
} from "../constants";

export async function translateContent(
  obj,
  {
    sourceLanguage,
    targetLanguage,
    fieldTypes,
    includeFields,
    excludeFields,
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

  function walkTranslatableFields(obj, fields) {
    for (const key in obj) {
      if (!obj[key]) continue;
      if (!fields[key]) continue;
      if (fields[key].translate === false) continue;
      if (!fieldTypes.includes(fields[key].type)) continue;

      // Include/exclude fields
      if (includeFields?.length && !includeFields.includes(key)) continue;
      if (excludeFields?.length && excludeFields.includes(key)) continue;

      // Handle text-like fields (collect for batch translation)
      if (["list", "text", "writer"].includes(fields[key].type)) {
        batchCollector.texts.push(obj[key]);
        batchCollector.callbacks.push((translatedText) => {
          obj[key] = translatedText;
        });
      }

      // Handle markdown content separately
      else if (["textarea", "markdown"].includes(fields[key].type)) {
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
        const text = obj[key].join(" | ");
        batchCollector.texts.push(text);
        batchCollector.callbacks.push((translatedText) => {
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
          for (const [rowIndex, row] of tableData.entries()) {
            if (!Array.isArray(row)) continue;

            for (const [colIndex, cell] of row.entries()) {
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
        for (const item of obj[key]) {
          walkTranslatableFields(item, fields[key].fields);
        }
      }

      // Handle object fields
      else if (fields[key].type === "object" && isObject(obj[key])) {
        walkTranslatableFields(obj[key], fields[key].fields);
      }

      // Handle layout fields
      else if (fields[key].type === "layout" && Array.isArray(obj[key])) {
        for (const layout of obj[key]) {
          for (const column of layout.columns) {
            for (const block of column.blocks) {
              if (!isBlockTranslatable(block)) continue;
              if (!fields[key].fieldsets[block.type]) continue;

              // if (!Object.keys(translatableBlocks).includes(block.type)) continue;

              const blockFields = flattenTabFields(
                fields[key].fieldsets,
                block,
              );
              walkTranslatableFields(block.content, blockFields);
            }
          }
        }
      }

      // Handle block fields
      else if (fields[key].type === "blocks" && Array.isArray(obj[key])) {
        for (const block of obj[key]) {
          if (!isBlockTranslatable(block)) continue;
          if (!fields[key].fieldsets[block.type]) continue;

          // if (!Object.keys(translatableBlocks).includes(block.type)) continue;

          const blockFields = flattenTabFields(fields[key].fieldsets, block);
          walkTranslatableFields(block.content, blockFields);
        }
      }
    }
  }

  walkTranslatableFields(obj, fields);

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

function isBlockTranslatable(block) {
  return isObject(block.content) && block.id && block.isHidden !== true;
}

function flattenTabFields(fieldsets, block) {
  const blockFields = {};

  for (const tab of Object.values(fieldsets[block.type].tabs)) {
    Object.assign(blockFields, tab.fields);
  }

  return blockFields;
}

function isObject(value) {
  return Object.prototype.toString.call(value) === "[object Object]";
}
