import { defineConfig } from "vitest/config";

export default defineConfig({
  define: {
    // Mirrors kirbyup at build-time
    __PLAYGROUND__: JSON.stringify(false),
  },
  test: {
    include: ["tests/panel/**/*.test.ts"],
  },
});
