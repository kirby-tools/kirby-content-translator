/** Must match `KirbyText::PLACEHOLDER_PATTERN` in PHP. */
export const PLACEHOLDER_PATTERN = /<c(\d+)\/>/g;

/** Mirrors Kirby's `Kirby\Text\KirbyTag` shape: type, optional value, attrs in source order. */
interface KirbyTag {
  type: string;
  value: string | undefined;
  attrs: [name: string, value: string][];
}

export function splitKirbyText(text: string, config: Record<string, string[]>) {
  const tagSpans = findKirbyTags(text);

  const proseParts: string[] = [];
  const attrValues: string[] = [];
  const tagSlots: {
    tag: KirbyTag;
    attrIndices: Map<string, number>;
  }[] = [];

  let cursor = 0;
  for (const [start, end] of tagSpans) {
    proseParts.push(text.slice(cursor, start));
    proseParts.push(`<c${tagSlots.length}/>`);

    const tag = parseKirbyTag(text.slice(start, end));
    const translatable = config[tag.type] ?? [];
    const attrIndices = new Map<string, number>();

    if (translatable.includes("value") && tag.value) {
      attrIndices.set("value", attrValues.length);
      attrValues.push(tag.value);
    }
    for (const [name, val] of tag.attrs) {
      if (translatable.includes(name) && val) {
        attrIndices.set(name, attrValues.length);
        attrValues.push(val);
      }
    }

    tagSlots.push({ tag, attrIndices });
    cursor = end;
  }
  proseParts.push(text.slice(cursor));

  const fragments = [proseParts.join(""), ...attrValues];

  function restore(translated: string[]): string {
    if (translated.length !== fragments.length) {
      throw new Error(
        `Expected ${fragments.length} translated fragments, got ${translated.length}`,
      );
    }

    const translatedProse = translated[0] ?? "";
    const translatedAttrs = translated.slice(1);

    return translatedProse.replace(PLACEHOLDER_PATTERN, (_, indexStr) => {
      const slot = tagSlots[Number.parseInt(indexStr, 10)];
      if (!slot) return "";
      return rebuildKirbyTag(slot.tag, slot.attrIndices, translatedAttrs);
    });
  }

  return { fragments, restore };
}

function findKirbyTags(text: string): [start: number, end: number][] {
  const spans: [number, number][] = [];
  const opener = /\([\w-]+:/g;
  // End of the most recently extracted span; matches inside it are skipped
  // so nested `(` inside a tag's value never start a new tag.
  let lastEnd = 0;
  for (const match of text.matchAll(opener)) {
    const start = match.index;
    if (start < lastEnd) continue;
    let depth = 0;
    let i = start;
    while (i < text.length) {
      if (text[i] === "(") depth++;
      else if (text[i] === ")") {
        depth--;
        if (depth === 0) {
          spans.push([start, i + 1]);
          lastEnd = i + 1;
          break;
        }
      }
      i++;
    }
  }
  return spans;
}

// Treat every `name:` token as an attribute boundary. The `kirbyTags` config
// lists which attrs to translate, not which are valid – restricting boundaries
// to it would let non-translatable attrs (class, rel, …) leak into values.
function parseKirbyTag(raw: string): KirbyTag {
  const body = raw.slice(1, -1);
  const colonIdx = body.indexOf(":");
  const type = body.slice(0, colonIdx).trim().toLowerCase();
  const rest = body.slice(colonIdx + 1);

  const boundary = /(?:^|\s+)([a-z][\w-]*):(?=\s|$)/gi;
  const matches: { name: string; index: number; matchEnd: number }[] = [];
  for (const match of rest.matchAll(boundary)) {
    matches.push({
      name: match[1]!.toLowerCase(),
      index: match.index,
      matchEnd: match.index + match[0].length,
    });
  }

  const valueEnd = matches.length === 0 ? rest.length : matches[0]!.index;
  const rawValue = rest.slice(0, valueEnd).trim();
  const value = rawValue === "" ? undefined : rawValue;

  const attrs: [string, string][] = [];
  for (let i = 0; i < matches.length; i++) {
    const current = matches[i]!;
    const next = matches[i + 1];
    const start = current.matchEnd;
    const end = next ? next.index : rest.length;
    attrs.push([current.name, rest.slice(start, end).trim()]);
  }

  return {
    type,
    value,
    attrs,
  };
}

function rebuildKirbyTag(
  tag: KirbyTag,
  attrIndices: Map<string, number>,
  translatedAttrs: string[],
) {
  const parts: string[] = [];

  const valueIndex = attrIndices.get("value");
  const value =
    valueIndex !== undefined ? translatedAttrs[valueIndex] : tag.value;
  parts.push(value ? `${tag.type}: ${value}` : tag.type);

  for (const [name, originalValue] of tag.attrs) {
    const idx = attrIndices.get(name);
    const finalValue = idx !== undefined ? translatedAttrs[idx] : originalValue;
    if (finalValue) {
      parts.push(`${name}: ${finalValue}`);
    }
  }

  return `(${parts.join(" ")})`;
}
