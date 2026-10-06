/**
 * Resolves a Kirby plural translation (forms separated by ` | `) to the form
 * for `count`. The form is picked before the values go in, so a value holding
 * ` | ` cannot shift the pick.
 */
export function translatePlural(
  t: (key: string, data?: Record<string, unknown>) => string,
  key: string,
  data: Record<string, unknown>,
  count: number,
) {
  const stringValues: string[] = [];
  // Private-use stand-ins hold neither braces nor ` | `, which the template
  // would read as a placeholder or a form boundary.
  const dataWithStandIns = Object.fromEntries(
    Object.entries(data).map(([name, value]) => {
      if (typeof value !== "string") return [name, value];
      stringValues.push(value);
      return [name, `${stringValues.length - 1}`];
    }),
  );

  const forms = t(key, dataWithStandIns).split(" | ");
  const form = (count === 1 ? forms[0] : forms[1]) ?? forms[0]!;

  return form.replace(
    /(\d+)/g,
    (_, index: string) => stringValues[Number(index)]!,
  );
}

/** Joins items as a list in the language of the Panel's interface. */
export function formatList(items: string[], translationCode: string) {
  return new Intl.ListFormat(translationCode.replace("_", "-")).format(items);
}
