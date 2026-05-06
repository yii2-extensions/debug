/**
 * Flat-config ESLint rules consumed by Super-Linter (and reproducible locally via `npm run lint:js`).
 *
 * Self-contained on purpose — Super-Linter resolves imports relative to this file's directory, where node_modules is
 * not available. Browser globals are declared inline; the only rules we touch are the two that conflict with the
 * project's defensive `try { ... } catch (_e) {}` pattern. Stylistic rules are intentionally left out: Prettier owns
 * formatting via `.prettierrc.json`.
 */
export default [
    {
        ignores: ["**/dist/**", "**/node_modules/**", "tests/runtime/**"],
    },
    {
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: "module",
            globals: {
                /* Storage + transport. */
                fetch: "readonly",
                localStorage: "readonly",
                Request: "readonly",
                URL: "readonly",
                XMLHttpRequest: "readonly",

                /* DOM + Web Components. */
                customElements: "readonly",
                document: "readonly",
                Event: "readonly",
                getComputedStyle: "readonly",
                HTMLElement: "readonly",
                IntersectionObserver: "readonly",
                matchMedia: "readonly",
                MutationObserver: "readonly",

                /* Window + history. */
                history: "readonly",
                window: "readonly",

                /* Language. */
                Reflect: "readonly",
            },
        },
        rules: {
            "no-empty": ["error", { allowEmptyCatch: true }],
            "no-unused-vars": [
                "error",
                {
                    argsIgnorePattern: "^_",
                    caughtErrorsIgnorePattern: "^_",
                    varsIgnorePattern: "^_",
                },
            ],
        },
    },
];
