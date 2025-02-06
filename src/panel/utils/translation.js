import { useApi } from "kirbyuse";
import pAll from "p-all";
import { TRANSLATE_API_ROUTE } from "../constants";

/**
 * Regex pattern to split markdown content by headings and paragraphs
 * while preserving line breaks
 */
const MARKDOWN_SEGMENT_PATTERN = /(?<=\n)(?=#{1,6}\s|[^#\n])/;

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

  function walkTranslatableFields(obj, fields) {
    for (const key in obj) {
      if (!obj[key]) continue;
      if (!fields[key]) continue;
      if (fields[key].translate === false) continue;
      if (!fieldTypes.includes(fields[key].type)) continue;

      // Include/exclude fields
      if (includeFields?.length && !includeFields.includes(key)) continue;
      if (excludeFields?.length && excludeFields.includes(key)) continue;

      // Handle text-like fields
      if (["list", "text", "textarea", "writer"].includes(fields[key].type)) {
        tasks.push(async () => {
          const response = await api.post(TRANSLATE_API_ROUTE, {
            sourceLanguage,
            targetLanguage,
            text: obj[key],
          });
          obj[key] = response.text;
        });
      }
      // Handle markdown content separately
      else if (["textarea", "markdown"].includes(fields[key].type)) {
        tasks.push(async () => {
          obj[key] = await translateMarkdown(obj[key]);
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

  async function translateMarkdown(text) {
    if (!text.trim()) return text;

    // Split content into segments while preserving line breaks
    const segments = text.split(MARKDOWN_SEGMENT_PATTERN).filter(Boolean);

    // Translate each segment while preserving trailing newlines
    const translatedSegments = await Promise.all(
      segments.map(async (segment) => {
        const trailingNewlines = segment.match(/\n*$/)?.[0] ?? "";
        const contentToTranslate = segment.trimEnd();

        if (!contentToTranslate) {
          return trailingNewlines;
        }

        const response = await api.post(TRANSLATE_API_ROUTE, {
          sourceLanguage,
          targetLanguage,
          text: contentToTranslate,
        });

        return response.text + trailingNewlines;
      }),
    );

    return translatedSegments.join("");
  }

  walkTranslatableFields(obj, fields);

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
