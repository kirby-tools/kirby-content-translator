/**
 * Resolves a Kirby plural translation string (separated by ` | `)
 * to the correct singular or plural form.
 */
export function formatPlural(text: string, count: number) {
  const parts = text.split(" | ");
  return count === 1 ? parts[0] : (parts[1] ?? parts[0]);
}
