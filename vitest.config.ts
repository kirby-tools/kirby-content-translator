import { defineConfig } from "vitest/config";

export default defineConfig({
  test: {
    include: ["tests/panel/**/*.test.ts"],
  },
});
