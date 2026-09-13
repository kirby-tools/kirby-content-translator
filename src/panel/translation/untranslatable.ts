import { PLACEHOLDER_PATTERN } from "./kirby-text";

/**
 * Matches text a translation provider would only corrupt: it either holds no
 * language at all, or holds a value – a price, a URL – that a translator will happily
 * localize into something broken.
 *
 * Structural emptiness ("this field has no content") is a separate question,
 * answered by the callers that walk the content.
 */
export function isUntranslatable(text: string): boolean {
  const trimmedText = text.trim();
  if (!trimmedText) return true;

  // Pure numeric, mirroring PHP's `is_numeric` so both pipelines count the same
  // values as untranslatable (signed, bare-decimal, trailing-dot, scientific
  // notation).
  if (/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?$/i.test(trimmedText))
    return true;

  if (/^https?:\/\/\S+$/i.test(trimmedText)) return true;

  // A textarea holding nothing but KirbyTags splits into prose that is only
  // KirbyTag placeholders – there is no language in it to translate.
  if (!trimmedText.replace(PLACEHOLDER_PATTERN, "").trim()) return true;

  return false;
}
