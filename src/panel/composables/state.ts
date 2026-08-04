import { effectScope } from "kirbyuse";

/**
 * Keeps state in the global scope so it is shared across Vue instances.
 *
 * @see https://vueuse.org/createGlobalState
 */
export function createGlobalState<Fn extends (...args: any[]) => any>(
  stateFactory: Fn,
): Fn {
  let isInitialized = false;
  let state: unknown;
  const scope = effectScope(true);

  return ((...args: any[]) => {
    if (!isInitialized) {
      state = scope.run(() => stateFactory(...args))!;
      isInitialized = true;
    }
    return state;
  }) as Fn;
}
