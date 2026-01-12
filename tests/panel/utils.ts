import type {
  KirbyAnyFieldProps,
  KirbyBlocksFieldProps,
  KirbyFieldProps,
  KirbyFieldsetProps,
  KirbyLayoutFieldProps,
  KirbyObjectFieldProps,
  KirbyStructureFieldProps,
} from "kirby-types";

/**
 * Helper to create a field definition with sensible defaults for testing.
 */
export function field<T extends Partial<KirbyAnyFieldProps>>(
  partial: T & { type: string; name: string },
): KirbyFieldProps {
  return {
    autofocus: false,
    disabled: false,
    hidden: false,
    required: false,
    saveable: true,
    translate: true,
    width: "1/1",
    ...partial,
  } as KirbyFieldProps;
}

/**
 * Helper to create a structure field definition for testing.
 */
export function structureField(
  name: string,
  fields: Record<string, KirbyFieldProps>,
): KirbyStructureFieldProps {
  return field({
    type: "structure",
    name,
    fields,
  }) as unknown as KirbyStructureFieldProps;
}

/**
 * Helper to create an object field definition for testing.
 */
export function objectField(
  name: string,
  fields: Record<string, KirbyFieldProps>,
): KirbyObjectFieldProps {
  return field({
    type: "object",
    name,
    fields,
  }) as unknown as KirbyObjectFieldProps;
}

/**
 * Helper to create a blocks field definition for testing.
 */
export function blocksField(
  name: string,
  blockTypes: Record<string, Record<string, KirbyFieldProps>>,
): KirbyBlocksFieldProps {
  const fieldsets = {} as KirbyBlocksFieldProps["fieldsets"];
  for (const [blockType, fields] of Object.entries(blockTypes)) {
    fieldsets[blockType] = {
      tabs: {
        content: {
          name: "content",
          fields,
        },
      },
    } as unknown as KirbyFieldsetProps;
  }

  return field({
    type: "blocks",
    name,
    fieldsets,
  }) as unknown as KirbyBlocksFieldProps;
}

/**
 * Helper to create a layout field definition for testing.
 */
export function layoutField(
  name: string,
  blockTypes: Record<string, Record<string, KirbyFieldProps>>,
): KirbyLayoutFieldProps {
  const fieldsets = {} as KirbyLayoutFieldProps["fieldsets"];
  for (const [blockType, fields] of Object.entries(blockTypes)) {
    fieldsets[blockType] = {
      tabs: {
        content: {
          name: "content",
          fields,
        },
      },
    } as unknown as KirbyFieldsetProps;
  }

  return field({
    type: "layout",
    name,
    fieldsets,
  }) as unknown as KirbyLayoutFieldProps;
}
