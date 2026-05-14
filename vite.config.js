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
    outDir: "src/assets/dist",
    emptyOutDir: true,
    cssCodeSplit: true,
    minify: "oxc",
    rollupOptions: {
      input: {
        debug: r("resources/src/core/debug.js"),
        "theme-toggle": r("resources/src/core/theme-toggle.js"),
        "history-cursor": r("resources/src/core/history-cursor.js"),
        db: r("resources/src/panels/db.js"),
        timeline: r("resources/src/panels/timeline.js"),
        userswitch: r("resources/src/panels/userswitch.js"),
        "phpinfo-search": r("resources/src/panels/phpinfo-search.js"),
        toolbar: r("resources/src/toolbar/index.js"),
        "css-main": r("resources/src/styles/main.entry.js"),
        "css-toolbar": r("resources/src/styles/toolbar.entry.js"),
        "css-timeline": r("resources/src/styles/timeline.entry.js"),
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
            rmSync(r(`src/assets/dist/js/${n}.min.js`));
          } catch {
            // shim js was already pruned.
          }
        });
      },
    },
  ],
});
