import { flattenTabFields } from "./shared";

/**
 * Filters content to only include syncable fields, respecting the `translate: false` setting
 * on fields, especially within blocks and layouts.
 *
 * @param {object} obj - The content object to filter
 * @param {object} options - Syncronization options
 * @param {object} options.fields - The field definitions
 * @param {Array<string>} options.fieldTypes - Array of field types to sync
 * @param {Array<string>} options.includeFields - Array of field names to include (optional)
 * @param {Array<string>} options.excludeFields - Array of field names to exclude (optional)
 * @returns {object} Filtered content object
 */
export function filterSyncableContent(
  obj,
  { fields, fieldTypes, includeFields = [], excludeFields = [] },
) {
  const syncableContent = {};

  for (const [key, value] of Object.entries(obj)) {
    const field = fields[key];

    // Skip if field doesn't exist or isn't a syncable type
    if (!field || !fieldTypes.includes(field.type)) continue;

    // Skip if field has translate = false
    if (field.translate === false) continue;

    // Apply include/exclude filters
    if (includeFields.length && !includeFields.includes(key)) continue;
    if (excludeFields.length && excludeFields.includes(key)) continue;

    // Handle blocks fields
    if (field.type === "blocks" && Array.isArray(value)) {
      syncableContent[key] = filterBlocksContent(value, field.fieldsets);
      continue;
    }

    // Handle layout fields (which contain blocks)
    if (field.type === "layout" && Array.isArray(value)) {
      syncableContent[key] = filterLayoutContent(value, field.fieldsets);
      continue;
    }

    // For all other fields, include as-is
    syncableContent[key] = value;
  }

  return syncableContent;
}

/**
 * Filters blocks content to only include fields that don't have translate = false
 *
 * @param {Array} blocks - Array of block objects
 * @param {object} fieldsets - Block fieldset definitions
 * @returns {Array} Filtered blocks array
 */
function filterBlocksContent(blocks, fieldsets) {
  return blocks.map((block) => {
    // If block type doesn't exist in fieldsets, return as-is
    if (!fieldsets[block.type]) return block;

    // Get all fields from all tabs in the block
    const blockFields = flattenTabFields(fieldsets, block);

    // Filter the block content
    const filteredContent = {};
    for (const [key, value] of Object.entries(block.content || {})) {
      const field = blockFields[key];

      // Skip if field doesn't exist or has translate = false
      if (!field || field.translate === false) continue;

      filteredContent[key] = value;
    }

    return {
      ...block,
      content: filteredContent,
    };
  });
}

/**
 * Filters layout content to only include fields that don't have translate = false
 *
 * @param {Array} layouts - Array of layout objects
 * @param {object} fieldsets - Block fieldset definitions
 * @returns {Array} Filtered layouts array
 */
function filterLayoutContent(layouts, fieldsets) {
  return layouts.map((layout) => ({
    ...layout,
    columns: layout.columns.map((column) => ({
      ...column,
      blocks: filterBlocksContent(column.blocks, fieldsets),
    })),
  }));
}
