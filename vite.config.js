import { defineConfig } from "vite";
import { resolve, dirname } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));
const r = (p) => resolve(here, p);

export default defineConfig({
  root: here,
  /* Relative asset URLs — the dist tree is published under a hashed path. */
  base: "",
  build: {
    outDir: "src/assets/dist",
    emptyOutDir: true,
    cssCodeSplit: true,
    target: "es2022",
    minify: "oxc",
    rollupOptions: {
      input: {
        debug: r("resources/src/core/debug.js"),
        toolbar: r("resources/src/toolbar/index.js"),
      },
      output: {
        entryFileNames: "js/[name].min.js",
        chunkFileNames: "js/[name].min.js",
        assetFileNames: (info) => {
          const name = info.names?.[0] ?? info.name ?? "";
          if (name.endsWith(".css")) {
            const base = name.replace(/^css-/, "").replace(/\.css$/, "");
            return `css/${base}.min.css`;
          }
          if (/\.(woff2?|ttf)$/.test(name)) {
            return "fonts/[name][extname]";
          }
          return "[ext]/[name].[ext]";
        },
        manualChunks: undefined,
      },
    },
  },
});
