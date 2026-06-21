/**
 * Flat-config ESLint rules for the client-side assets, reproducible locally via `npm run lint:js`.
 *
 * Self-contained on purpose so CI jobs and local runs can resolve this file without extra imports. Browser globals are
 * declared inline; the only rules we touch are the two that conflict with the
 * project's defensive `try { ... } catch (_e) {}` pattern. Stylistic rules are intentionally left out: Prettier owns
 * formatting via `.prettierrc.json`.
 */
export default [
    {
        ignores: ["**/dist/**", "**/node_modules/**", "runtime/**"],
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
