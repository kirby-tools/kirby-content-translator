/**
 * Checks if a string value should be skipped from translation.
 *
 * Returns `true` for:
 * - Empty or whitespace-only strings
 * - Pure numeric values (integers, floats, scientific notation)
 * - Pure URLs (`http://` or `https://`)
 */
export function shouldSkipTranslation(value: string): boolean {
  const trimmedValue = value.trim();
  if (!trimmedValue) return true;

  // Pure numeric (including negative, floats, scientific notation)
  if (/^-?\d+(?:\.\d+)?(?:e[+-]?\d+)?$/i.test(trimmedValue)) return true;

  // Pure URL
  if (/^https?:\/\/\S+$/i.test(trimmedValue)) return true;

  return false;
}
