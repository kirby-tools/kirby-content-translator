import process from "node:process";
import { defineConfig } from "kirbyup/config";
import UnoCSS from "unocss/vite";

export default defineConfig({
  vite: {
    plugins: [UnoCSS()],
    define: {
      __PLAYGROUND__: JSON.stringify(process.env.PLAYGROUND === "true"),
    },
  },
});
