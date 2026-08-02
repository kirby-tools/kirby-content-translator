/**
 * Returns `true` for empty, numeric, or bare-URL strings.
 */
export function shouldSkipTranslation(value: string): boolean {
  const trimmedValue = value.trim();
  if (!trimmedValue) return true;

  // Pure numeric, mirroring PHP's `is_numeric` so both pipelines skip the
  // same values (signed, bare-decimal, trailing-dot, scientific notation)
  if (/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?$/i.test(trimmedValue))
    return true;

  if (/^https?:\/\/\S+$/i.test(trimmedValue)) return true;

  return false;
}
