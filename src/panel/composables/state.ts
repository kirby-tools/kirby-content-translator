import { effectScope } from "kirbyuse";

/**
 * Keep states in the global scope to be reusable across Vue instances.
 *
 * @see https://vueuse.org/createGlobalState
 * @param stateFactory A factory function to create the state
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
