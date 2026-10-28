import { vi } from "vitest";

export function baseKirbyuseMock() {
  return {
    createLogger: () => ({
      info: vi.fn(),
      warn: vi.fn(),
      error: vi.fn(),
      debug: vi.fn(),
    }),
  };
}
