import type {
  KirbyBlock,
  KirbyBlocksFieldProps,
  KirbyFieldProps,
  KirbyLayout,
  KirbyLayoutFieldProps,
} from "kirby-types";
import { flattenTabFields } from "./fields";

/**
 * Filters content to syncable fields only, honouring `translate: false` on nested blocks and layouts.
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

    if (!field || !fieldTypes.includes(field.type)) continue;
    if (field.translate === false) continue;
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

    syncableContent[key] = value;
  }

  return syncableContent;
}

function filterBlocksContent(
  blocks: KirbyBlock[],
  fieldsets: KirbyBlocksFieldProps["fieldsets"],
): KirbyBlock[] {
  return blocks.map((block) => {
    if (!fieldsets[block.type]) return block;

    const blockFields = flattenTabFields(fieldsets, block);
    const filteredContent: Record<string, unknown> = {};

    for (const [key, value] of Object.entries(block.content || {})) {
      const field = blockFields[key];
      if (!field || field.translate === false) continue;
      filteredContent[key] = value;
    }

    return {
      ...block,
      content: filteredContent,
    };
  });
}

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
