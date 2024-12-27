import { useApi } from "kirbyuse";
import pAll from "p-all";
import { TRANSLATE_API_ROUTE } from "../constants";

export async function translateContent(
  obj,
  {
    sourceLanguage,
    targetLanguage,
    fieldTypes,
    includeFields,
    excludeFields,
    fields,
  },
) {
  const api = useApi();
  const tasks = [];

  function handleTranslation(obj, fields) {
    for (const key in obj) {
      if (!obj[key]) continue;
      if (!fields[key]) continue;
      if (!fields[key].translate) continue;
      if (!fieldTypes.includes(fields[key].type)) continue;

      // Include/exclude fields
      if (includeFields?.length && !includeFields.includes(key)) continue;
      if (excludeFields?.length && excludeFields.includes(key)) continue;

      // Handle text-like fields
      if (
        ["list", "text", "textarea", "writer", "markdown"].includes(
          fields[key].type,
        )
      ) {
        tasks.push(async () => {
          const response = await api.post(TRANSLATE_API_ROUTE, {
            sourceLanguage,
            targetLanguage,
            text: obj[key],
          });
          obj[key] = response.text;
        });
      }

      // Handle tags fields
      else if (
        fields[key].type === "tags" &&
        Array.isArray(obj[key]) &&
        obj[key].length
      ) {
        // Improve performance by translating all tags in a single request
        const text = obj[key].join(" | ");
        tasks.push(async () => {
          const response = await api.post(TRANSLATE_API_ROUTE, {
            sourceLanguage,
            targetLanguage,
            text,
          });
          obj[key] = response.text.split("|").map((tag) => tag.trim());
        });
      }

      // Handle structure fields
      else if (fields[key].type === "structure" && Array.isArray(obj[key])) {
        for (const item of obj[key]) {
          handleTranslation(item, fields[key].fields);
        }
      }

      // Handle object fields
      else if (fields[key].type === "object" && isObject(obj[key])) {
        handleTranslation(obj[key], fields[key].fields);
      }

      // Handle layout fields
      else if (fields[key].type === "layout" && Array.isArray(obj[key])) {
        for (const layout of obj[key]) {
          for (const column of layout.columns) {
            for (const block of column.blocks) {
              if (!isBlockTranslatable(block)) continue;
              if (!fields[key].fieldsets[block.type]) continue;

              // if (!Object.keys(translatableBlocks).includes(block.type)) continue;

              const blockFields = reduceFieldsFromTabs(
                fields[key].fieldsets,
                block,
              );
              handleTranslation(block.content, blockFields);
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

          const blockFields = reduceFieldsFromTabs(
            fields[key].fieldsets,
            block,
          );
          handleTranslation(block.content, blockFields);
        }
      }
    }
  }

  handleTranslation(obj, fields);

  // Process translation tasks in batches
  try {
    await pAll(tasks, { concurrency: 5 });
  } catch (error) {
    console.error(error);
    throw error;
  }

  return obj;
}

function isBlockTranslatable(block) {
  return isObject(block.content) && block.id && block.isHidden !== true;
}

function reduceFieldsFromTabs(fieldsets, block) {
  const blockFields = {};

  for (const tab of Object.values(fieldsets[block.type].tabs)) {
    Object.assign(blockFields, tab.fields);
  }

  return blockFields;
}

function isObject(value) {
  return Object.prototype.toString.call(value) === "[object Object]";
}
