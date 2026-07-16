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

  // Pure numeric, mirroring PHP's `is_numeric` so both pipelines skip the
  // same values (signed, bare-decimal, trailing-dot, scientific notation)
  if (/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?$/i.test(trimmedValue))
    return true;

  // Pure URL
  if (/^https?:\/\/\S+$/i.test(trimmedValue)) return true;

  return false;
}
