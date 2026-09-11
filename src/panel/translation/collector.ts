import type {
  KirbyBlock,
  KirbyBlocksFieldProps,
  KirbyFieldProps,
  KirbyLayout,
  KirbyLayoutFieldProps,
  KirbyObjectFieldProps,
  KirbyStructureFieldProps,
} from "kirby-types";
import type {
  CollectedTranslation,
  CollectorOptions,
  CollectorResult,
} from "./types";
import { isObject } from "utilful";
import * as yaml from "yaml";
import { flattenTabFields, isBlockTranslatable } from "../utils/fields";
import { splitKirbyText } from "./kirby-text";

interface CollectorContext {
  options: CollectorOptions;
  translations: CollectedTranslation[];
  finalizers: (() => void)[];
}

/**
 * Walks content and returns translatable units with their
 * apply callbacks and post-translation finalizers.
 *
 * Emits every unit that holds content, including ones a provider would only
 * corrupt – `translateUnits` makes that call once, downstream, where it also
 * sees the units this function fans out of a KirbyText.
 */
export function collectTranslations(
  obj: Record<string, unknown>,
  options: CollectorOptions,
): CollectorResult {
  const context: CollectorContext = {
    options,
    translations: [],
    finalizers: [],
  };

  // Apply include/exclude filters by narrowing the top-level fields.
  const { includeFields = [], excludeFields = [] } = options;
  let { fields } = options;

  if (includeFields.length || excludeFields.length) {
    fields = Object.fromEntries(
      Object.entries(fields).filter(([key]) => {
        if (includeFields.length && !includeFields.includes(key)) return false;
        if (excludeFields.length && excludeFields.includes(key)) return false;
        return true;
      }),
    );
  }

  collectFromObject(obj, fields, context);

  return {
    translations: context.translations,
    finalizers: context.finalizers,
  };
}

/**
 * Walks the fields of one content object. `parentFieldKey` is the `fieldKey` of
 * the field that holds `obj`, so a nested unit's `fieldKey` leads with its
 * top-level field.
 */
function collectFromObject(
  obj: Record<string, unknown>,
  fields: Record<string, KirbyFieldProps>,
  context: CollectorContext,
  parentFieldKey?: string,
) {
  const { fieldTypes } = context.options;

  for (const key in obj) {
    const value = obj[key];

    if (value === null || value === undefined || value === "") continue;
    if (Array.isArray(value) && value.length === 0) continue;
    if (!fields[key]) continue;
    if (fields[key].translate === false) continue;
    if (!fieldTypes.includes(fields[key].type)) continue;

    collectFromField(obj, key, value, fields[key], context, parentFieldKey);
  }
}

function collectFromField(
  obj: Record<string, unknown>,
  key: string,
  value: unknown,
  field: KirbyFieldProps,
  context: CollectorContext,
  parentFieldKey?: string,
) {
  const fieldKey = parentFieldKey ? `${parentFieldKey}.${key}` : key;

  if (["list", "text", "writer"].includes(field.type)) {
    if (typeof value !== "string" || !value) return;

    context.translations.push({
      unit: {
        text: value,
        fieldKey,
      },
      apply(translatedText) {
        obj[key] = translatedText;
      },
    });
  }

  // KirbyTags are split out so their structure survives translation intact and
  // is reassembled in a finalizer once every unit came back.
  else if (["textarea", "markdown"].includes(field.type)) {
    if (typeof value !== "string" || !value) return;

    const { unitTexts, restore } = splitKirbyText(
      value,
      context.options.kirbyTags ?? {},
    );
    const translated: string[] = Array.from({ length: unitTexts.length });

    for (const [i, unitText] of unitTexts.entries()) {
      context.translations.push({
        unit: { text: unitText, fieldKey },
        apply(translatedText) {
          translated[i] = translatedText;
        },
      });
    }

    context.finalizers.push(() => {
      obj[key] = restore(translated);
    });
  } else if (field.type === "tags") {
    const tags = value;
    if (!Array.isArray(tags) || !tags.length) return;

    const text = (tags as string[]).join(" | ");
    context.translations.push({
      unit: {
        text,
        fieldKey,
      },
      apply(translatedText) {
        obj[key] = translatedText.split("|").map((tag) => tag.trim());
      },
    });
  } else if (field.type === "table") {
    collectFromTableField(obj, key, fieldKey, value, context);
  } else if (field.type === "structure" && Array.isArray(value)) {
    const structureField = field as KirbyStructureFieldProps;
    for (const item of value) {
      if (isObject(item)) {
        collectFromObject(item, structureField.fields, context, fieldKey);
      }
    }
  } else if (field.type === "object" && isObject(value)) {
    const objectField = field as KirbyObjectFieldProps;
    collectFromObject(value, objectField.fields, context, fieldKey);
  } else if (field.type === "layout" && Array.isArray(value)) {
    const layoutField = field as KirbyLayoutFieldProps;
    for (const layout of value as KirbyLayout[]) {
      for (const column of layout.columns ?? []) {
        for (const block of column.blocks ?? []) {
          if (!isBlockTranslatable(block)) continue;
          if (!layoutField.fieldsets[block.type]) continue;

          const blockFields = flattenTabFields(layoutField.fieldsets, block);
          collectFromObject(block.content, blockFields, context, fieldKey);
        }
      }
    }
  } else if (field.type === "blocks" && Array.isArray(value)) {
    const blocksField = field as KirbyBlocksFieldProps;
    for (const block of value as KirbyBlock[]) {
      if (!isBlockTranslatable(block)) continue;
      if (!blocksField.fieldsets[block.type]) continue;

      const blockFields = flattenTabFields(blocksField.fieldsets, block);
      collectFromObject(block.content, blockFields, context, fieldKey);
    }
  }
}

function collectFromTableField(
  obj: Record<string, unknown>,
  key: string,
  fieldKey: string,
  value: unknown,
  context: CollectorContext,
) {
  let tableData = value as string | string[][];
  let isYamlEncoded = false;

  // Parse YAML-encoded table data from the Panel.
  if (typeof tableData === "string") {
    isYamlEncoded = true;
    try {
      tableData = yaml.parse(tableData) as string[][];
    } catch (error) {
      console.error(
        `Failed to parse table field "${fieldKey}" as YAML:`,
        error,
      );
      return;
    }
  }

  if (!Array.isArray(tableData)) return;

  // Store reference for cell updates and YAML serialization.
  const tableRef = tableData;

  for (const [rowIndex, row] of tableData.entries()) {
    if (!Array.isArray(row)) continue;

    for (const [colIndex, cell] of row.entries()) {
      if (!cell || typeof cell !== "string" || !cell.trim()) continue;

      context.translations.push({
        unit: {
          text: cell,
          fieldKey: `${fieldKey}[${rowIndex}][${colIndex}]`,
        },
        apply(translatedText) {
          tableRef[rowIndex]![colIndex] = translatedText;
        },
      });
    }
  }

  // Update the object reference (handles non-YAML case).
  obj[key] = tableData;

  if (isYamlEncoded) {
    context.finalizers.push(() => {
      const rowStrings = tableRef.map((row) => {
        if (!Array.isArray(row)) return "";
        return yaml
          .stringify(row)
          .trim()
          .split("\n")
          .map((line) => `  ${line}`)
          .join("\n");
      });

      obj[key] = rowStrings.map((rowString) => `-\n${rowString}`).join("\n");
    });
  }
}
