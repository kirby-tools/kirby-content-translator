/**
 * Checks if a block is translatable based on its content structure and visibility
 *
 * @param {object} block - Block object to check
 * @returns {boolean} True if the block is translatable
 */
export function isBlockTranslatable(block) {
  return isObject(block.content) && block.id && block.isHidden !== true;
}

/**
 * Flattens tab fields from fieldsets into a single object
 *
 * @param {object} fieldsets - Block fieldset definitions
 * @param {object} block - Block object with type property
 * @returns {object} Flattened fields object
 */
export function flattenTabFields(fieldsets, block) {
  const blockFields = {};

  for (const tab of Object.values(fieldsets[block.type].tabs)) {
    Object.assign(blockFields, tab.fields);
  }

  return blockFields;
}

/**
 * Checks if a value is a plain object
 *
 * @param {*} value - Value to check
 * @returns {boolean} True if the value is a plain object
 */
export function isObject(value) {
  return Object.prototype.toString.call(value) === "[object Object]";
}
