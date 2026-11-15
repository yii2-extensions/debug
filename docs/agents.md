# AI assistance

The package owns [`skills/yii2-debug-inspection/SKILL.md`](../skills/yii2-debug-inspection/SKILL.md). It helps agents
configure this debugger in a Yii 22 application, inspect captured requests and register optional providers. It does not
install the debugger or change application permissions.

## Install in an application

### Automatic Composer installation

Complete the
[one-time application setup](https://yii2-framework.github.io/docs/project/ai-agents/#composer-installation) to enable
`llm/skills` and set `auto-sync` to `true` in `skills.json`. Approve `yii2-extensions/debug` explicitly in
`dependencies.composer.trusted`. The installed package must include its skill bundle; its `extra.skills.source` declares
the directory.

```bash
composer install
composer skills:show
```

With `auto-sync` enabled, later dependency installs and updates synchronize the skill automatically; no per-skill
installer is required. If `auto-sync` is `false`, run `composer skills:update` after dependency installation or updates.

### Optional npx installation

Run the pinned installer from the application's root. For a local checkout:

```bash
npx skills@1.7.2 add /absolute/path/to/debug --list
npx skills@1.7.2 add /absolute/path/to/debug \
  --skill yii2-debug-inspection --agent claude-code codex opencode
```

A Composer-installed package may also be a source when it contains the skill directory. Installing PHP dependencies
alone does not expose a skill under `vendor/` to clients opened at the application root. For a public source, first run
`npx skills@1.7.2 add yii2-extensions/debug --list` and install only after listing confirms the skill is on the default
branch. New local work is not yet a published skill.

The installer uses `.agents/skills/` for Codex/OpenCode and `.claude/skills/` for Claude Code. This repository links
those paths to the canonical `skills/` directory. For manual installation, retain the complete named skill directory.
Avoid separately edited client copies. The documentation site distributes the package bundle with provenance and
checksums rather than owning another editable version.

## Verify activation and results

Open a fresh client session at the application root and check its skill list. Explicitly activate
`$yii2-debug-inspection` in Codex, `/yii2-debug-inspection` in Claude Code, or ask OpenCode to load the skill through
its skill tool. A listing or a manual Markdown read does not prove native discovery or activation.

Use a realistic task: enable the debugger only in development, make an HTML application request, inspect History and a
log entry, then verify access is denied to an unlisted address. For Vite, request a page that actually uses the
component and confirm a captured event. Do not infer provider capture from panel registration alone. Keep production
disabled and do not share raw captures without reviewing their contents.

The [configuration recipes](examples.md) can be copied into temporary PHP files and checked without a running browser;
an application request and native client activation are separate checks. See [testing](testing.md).

## Example prompts

Start a fresh client session at the application root. The explicit example uses Codex's `$skill-name` notation; use
`/yii2-debug-inspection` in Claude Code or the installed client's native skill mechanism. Do not paste the skill body
and count that as native activation. These are tasks to execute and verify, not claims that an evaluation has passed.

### Explicit invocation

```text
$yii2-debug-inspection
Show development-only Yii 22 debugger configuration with a narrow IP allowlist. Demonstrate how to verify an allowed
request and a denied request without logging secrets or exposing captured data publicly.
Inspect the installed revision first. Do not install dependencies or change files without authorization.
```

### Natural selection

```text
My JSON request has no debugger toolbar. How can I inspect its capture and verify that the debugger remains disabled
in production?
```

### Verify the outcome

Check configuration guards, access denial, response headers and the captured request; a JSON response is not expected to
contain injected HTML. Record native loading evidence separately from configuration review, executable checks and
browser/runtime results. For a negative selection control, ask an unrelated question in a fresh session and confirm this
package skill is not loaded. Keep evaluation traces private; report unavailable checks rather than treating discovery as
execution.

[Back to documentation](../README.md#documentation)
