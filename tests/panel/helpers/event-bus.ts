/** Stands in for `panel.events`. */
export function createEventBus() {
  const listeners = new Map<string, Set<(payload: unknown) => void>>();

  return {
    on(event: string, handler: (payload: unknown) => void) {
      listeners.set(event, (listeners.get(event) ?? new Set()).add(handler));
    },
    off(event: string, handler: (payload: unknown) => void) {
      listeners.get(event)?.delete(handler);
    },
    emit(event: string, payload: unknown) {
      for (const handler of listeners.get(event) ?? []) handler(payload);
    },
    clear() {
      listeners.clear();
    },
  };
}
