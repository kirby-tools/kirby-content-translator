import { vi } from "vitest";
import { computed, effectScope, reactive, ref, watch } from "vue";

export function baseKirbyuseMock() {
  return {
    ref,
    computed,
    reactive,
    watch,
    effectScope,
    createLogger: () => ({
      info: vi.fn(),
      warn: vi.fn(),
      error: vi.fn(),
      debug: vi.fn(),
    }),
  };
}
