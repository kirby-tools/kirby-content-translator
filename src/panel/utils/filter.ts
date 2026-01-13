import type {
  KirbyBlock,
  KirbyBlocksFieldProps,
  KirbyFieldProps,
  KirbyLayout,
  KirbyLayoutFieldProps,
} from "kirby-types";
import { flattenTabFields } from "./fields";

/**
 * Filters content to only include syncable fields, respecting the `translate: false` setting
 * on fields, especially within blocks and layouts.
 */
export function filterSyncableContent(
  obj: Record<string, unknown>,
  {
    fields,
    fieldTypes,
    includeFields = [],
    excludeFields = [],
  }: {
    fields: Record<string, KirbyFieldProps>;
    fieldTypes: readonly string[] | string[];
    includeFields?: string[];
    excludeFields?: string[];
  },
) {
  const syncableContent: Record<string, unknown> = {};

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
      syncableContent[key] = filterBlocksContent(
        value as KirbyBlock[],
        (field as KirbyBlocksFieldProps).fieldsets,
      );
      continue;
    }

    // Handle layout fields (which contain blocks)
    if (field.type === "layout" && Array.isArray(value)) {
      syncableContent[key] = filterLayoutContent(
        value as KirbyLayout[],
        (field as KirbyLayoutFieldProps).fieldsets,
      );
      continue;
    }

    // For all other fields, include as-is
    syncableContent[key] = value;
  }

  return syncableContent;
}

/**
 * Filters blocks content to only include fields that don't have translate = false
 */
function filterBlocksContent(
  blocks: KirbyBlock[],
  fieldsets: KirbyBlocksFieldProps["fieldsets"],
): KirbyBlock[] {
  return blocks.map((block) => {
    // If block type doesn't exist in fieldsets, return as-is
    if (!fieldsets[block.type]) return block;

    // Get all fields from all tabs in the block
    const blockFields = flattenTabFields(fieldsets, block);

    // Filter the block content
    const filteredContent: Record<string, unknown> = {};
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
 */
function filterLayoutContent(
  layouts: KirbyLayout[],
  fieldsets: KirbyLayoutFieldProps["fieldsets"],
): KirbyLayout[] {
  return layouts.map((layout) => ({
    ...layout,
    columns: layout.columns.map((column) => ({
      ...column,
      blocks: filterBlocksContent(column.blocks, fieldsets),
    })),
  }));
}
