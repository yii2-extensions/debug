import { defineConfig } from "vite";
import { resolve, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { rmSync } from "node:fs";

const here = dirname(fileURLToPath(import.meta.url));
const r = (p) => resolve(here, p);

const cssShimEntries = ["css-main", "css-toolbar", "css-timeline"];

export default defineConfig({
  root: here,
  build: {
    outDir: "dist",
    emptyOutDir: true,
    cssCodeSplit: true,
    minify: "oxc",
    rollupOptions: {
      input: {
        debug: r("src/core/debug.js"),
        "theme-toggle": r("src/core/theme-toggle.js"),
        "history-cursor": r("src/core/history-cursor.js"),
        db: r("src/panels/db.js"),
        timeline: r("src/panels/timeline.js"),
        userswitch: r("src/panels/userswitch.js"),
        "phpinfo-search": r("src/panels/phpinfo-search.js"),
        toolbar: r("src/toolbar/index.js"),
        "css-main": r("src/styles/main.entry.js"),
        "css-toolbar": r("src/styles/toolbar.entry.js"),
        "css-timeline": r("src/styles/timeline.entry.js"),
      },
      output: {
        entryFileNames: "js/[name].js",
        chunkFileNames: "js/[name].js",
        assetFileNames: (info) => {
          const name = info.names?.[0] ?? info.name ?? "";
          if (name.endsWith(".css")) {
            const base = name.replace(/^css-/, "").replace(/\.css$/, "");
            return `css/${base}.min.css`;
          }
          return "[ext]/[name].[ext]";
        },
        manualChunks: undefined,
      },
    },
  },
  plugins: [
    {
      name: "drop-css-shim-js",
      closeBundle() {
        cssShimEntries.forEach((n) => {
          try {
            rmSync(r(`dist/js/${n}.js`));
          } catch {
            // shim js was already pruned.
          }
        });
      },
    },
  ],
});
