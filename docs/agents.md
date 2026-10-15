# AI assistance

The package owns [`skills/yii2-debug-inspection/SKILL.md`](../skills/yii2-debug-inspection/SKILL.md). It helps agents
configure this debugger in a Yii 22 application, inspect captured requests and register optional providers.
It does not install the debugger or change application permissions.

## Install in an application

Run the pinned installer from the application's root. For a local checkout:

```bash
npx skills@1.7.2 add /absolute/path/to/debug --list
npx skills@1.7.2 add /absolute/path/to/debug \
  --skill yii2-debug-inspection --agent claude-code codex opencode
```

A Composer-installed package may also be a source when it contains the skill directory. Installing PHP dependencies
alone does not expose a skill under `vendor/` to clients opened at the application root. For a public source,
first run `npx skills@1.7.2 add yii2-extensions/debug --list` and install only after listing confirms the skill is
on the default branch. New local work is not yet a published skill.

The installer uses `.agents/skills/` for Codex/OpenCode and `.claude/skills/` for Claude Code. This repository
links those paths to the canonical `skills/` directory. For manual installation, retain the complete named skill
directory. Avoid separately edited client copies. The documentation site distributes the package bundle with
provenance and checksums rather than owning another editable version.

## Verify activation and results

Open a fresh client session at the application root and check its skill list. Explicitly activate
`$yii2-debug-inspection` in Codex, `/yii2-debug-inspection` in Claude Code, or ask OpenCode to load the skill through
its skill tool. A listing or a manual Markdown read does not prove native discovery or activation.

Use a realistic task: enable the debugger only in development, make an HTML application request, inspect History
and a log entry, then verify access is denied to an unlisted address. For Vite, request a page that actually uses
the component and confirm a captured event. Do not infer provider capture from panel registration alone.
Keep production disabled and do not share raw captures without reviewing their contents.

The executable [configuration examples](examples.md) can be checked without a running browser; an application
request and native client activation are separate checks. See [testing](testing.md).
