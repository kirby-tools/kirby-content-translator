import process from "node:process";
import { defineConfig } from "kirbyup/config";

export default defineConfig({
  vite: {
    define: {
      __PLAYGROUND__: JSON.stringify(process.env.PLAYGROUND === "true"),
    },
  },
});
