# Repository Guidelines

## Project Structure & Module Organization
`Prompts.md` is the canonical brief—treat it as read-only. Keep the WordPress bootstrap (`autosalevps.php`) at the root and require everything else from there. Source lives in `src/`: `src/ui` for modals/logs, `src/core` for config handling, polling, and LLM calls. Build artifacts land in `assets/js` and `assets/css`, while default TOML files remain in `config/` so they ship inside the ZIP yet stay editable locally.

## Build, Test, and Development Commands
Install dependencies with `npm install`. Use `npm run dev -- --host` for hot reload, `npm run build` to emit minified assets, and `npm run package` to create `AutoSaleVPS.zip` for upload. Whenever PHP utilities change, run `composer install` and `composer lint` so hooks and shortcodes stay compatible.

## Coding Style & Naming Conventions
JavaScript and CSS use 2-space indentation; PHP mirrors WordPress tabs plus snake_case functions. Name UI modules with the `ASV` prefix (for instance `ASVLogPanel.ts`) and keep TOML keys nested like `vps.<vendor>.<pid>`. Run `npm run lint` (ESLint + Stylelint) and `composer lint` before committing to keep formatting consistent.

## Testing Guidelines
`npm run test` (Vitest) covers UI/state logic under `tests/ui` and network/LLM helpers under `tests/core`. Name files after the feature (`availability.spec.ts`). Document any manual WordPress checks and screenshots in `docs/test-notes.md` until e2e automation lands, and require at least one automated test for every new component.

## Commit & Pull Request Guidelines
Structure commits as `type(scope): message` using `feat`, `fix`, `docs`, `chore`, or `test`, keep subjects under 72 characters, and cite related issues (`Refs #12`). Pull requests must summarize the change, attach screenshots or GIFs for UI updates (include modal + log window), and confirm `npm run lint`, `npm run test`, `npm run build`, and `npm run package` succeed. Always request a reviewer; no self-merges unless a hotfix is blocking.

## Security & Configuration Tips
Treat `API_KEY` as secret—never log, echo, or commit it, and redact it from screenshots. Validate TOML edits server-side so `sale_format`/`valid_format` stay well formed before saving. Availability probes must exclusively hit `valid_format` URLs and honor the `valid_vps_time` delay to avoid hammering RackNerd. The “eye” preview around secrets should remain hover-only and immediately hide once the pointer leaves.
