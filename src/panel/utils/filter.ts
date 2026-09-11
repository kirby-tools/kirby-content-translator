import type {
  KirbyBlock,
  KirbyBlocksFieldProps,
  KirbyFieldProps,
  KirbyLayout,
  KirbyLayoutFieldProps,
} from "kirby-types";
import { flattenTabFields } from "./fields";

/** Tells whether the configuration admits a top-level field to import and translation. */
export function isEligibleField(
  name: string,
  field: KirbyFieldProps,
  {
    fieldTypes,
    includeFields = [],
    excludeFields = [],
  }: {
    fieldTypes: readonly string[] | string[];
    includeFields?: string[];
    excludeFields?: string[];
  },
) {
  return (
    field.translate !== false &&
    fieldTypes.includes(field.type) &&
    (includeFields.length === 0 || includeFields.includes(name)) &&
    !excludeFields.includes(name)
  );
}

/**
 * Filters content to eligible fields only, honoring `translate: false` on nested blocks and layouts.
 */
export function filterEligibleContent(
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
  const eligibleContent: Record<string, unknown> = {};

  for (const [key, value] of Object.entries(obj)) {
    const field = fields[key];

    if (
      !field ||
      !isEligibleField(key, field, { fieldTypes, includeFields, excludeFields })
    ) {
      continue;
    }

    if (field.type === "blocks" && Array.isArray(value)) {
      eligibleContent[key] = filterBlocksContent(
        value as KirbyBlock[],
        (field as KirbyBlocksFieldProps).fieldsets,
      );
      continue;
    }

    // Layouts nest their blocks inside columns, so they need their own walk.
    if (field.type === "layout" && Array.isArray(value)) {
      eligibleContent[key] = filterLayoutContent(
        value as KirbyLayout[],
        (field as KirbyLayoutFieldProps).fieldsets,
      );
      continue;
    }

    eligibleContent[key] = value;
  }

  return eligibleContent;
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
