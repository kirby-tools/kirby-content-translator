import type {
  KirbyBlock,
  KirbyBlocksFieldProps,
  KirbyFieldProps,
  KirbyLayoutFieldProps,
} from "kirby-types";

/**
 * Checks if a block is translatable based on its content structure and visibility
 */
export function isBlockTranslatable(block: KirbyBlock) {
  return isObject(block.content) && !!block.id && block.isHidden !== true;
}

/**
 * Flattens tab fields from fieldsets into a single object
 */
export function flattenTabFields(
  fieldsets:
    | KirbyBlocksFieldProps["fieldsets"]
    | KirbyLayoutFieldProps["fieldsets"],
  block: KirbyBlock,
): Record<string, KirbyFieldProps> {
  const flattenedFields: Record<string, KirbyFieldProps> = {};
  const blockFieldset = fieldsets[block.type];

  if (!blockFieldset?.tabs) {
    return flattenedFields;
  }

  for (const tab of Object.values(blockFieldset.tabs)) {
    Object.assign(flattenedFields, tab.fields);
  }

  return flattenedFields;
}

export function isObject(value: unknown): value is Record<any, any> {
  return Object.prototype.toString.call(value) === "[object Object]";
}
