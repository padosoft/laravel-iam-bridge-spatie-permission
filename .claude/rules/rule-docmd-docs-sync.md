# RULE — keep the docmd docs-site in sync (binding)

**This rule is mandatory and blocking.** Whenever you add or change a **user-facing feature** of
laravel-iam-bridge-spatie-permission, or update the README in a substantive way, you **MUST** update the
corresponding docmd page under `docs-site/docs/**` in the **same** unit of work — following the `docmd-docs`
skill.

## When it applies (you MUST update the docs-site)

- A new or changed **artisan command** (`src/Console/`, e.g. `iam:spatie:scan`, `iam:spatie:manifest`),
  including any option/signature change → update `docs-site/docs/reference/cli.md` and the matching guide.
- A change to the **migration toolkit** (`src/Migration/SpatieScanner`, `PermissionMapper`,
  `ManifestGenerator`) — scan output shape, slugging algorithm, risk heuristic, dedup, manifest fields →
  update `docs-site/docs/guides/inventory-and-scan.md`, `manifest-generation.md`,
  `docs-site/docs/concepts/permission-slugging.md`, and `docs-site/docs/architecture/manifest-contract.md` /
  `docs-site/docs/reference/manifest-schema.md`.
- A change to the **shadow runtime** (`src/Shadow/ShadowGate`, `RecordsMismatch`, `MismatchRecorder`) —
  comparison logic, the direct Spatie probe, deny-overrides, the `iam.shadow.mismatch` record shape →
  update `docs-site/docs/guides/shadow-mode.md`, `docs-site/docs/concepts/decision-diffing.md`,
  `docs-site/docs/architecture/shadow-gate.md`, and `docs-site/docs/operations/observability.md`.
- A new or changed **config key** (`config/iam-spatie.php`: `mode`, `application`, `cache.*`,
  `mismatch_log_channel`) → update `docs-site/docs/operations/configuration.md`.
- A change to the **cutover/rollback** behavior or the `IAM_SPATIE_MODE` semantics → update
  `docs-site/docs/guides/cutover-and-rollback.md` and `docs-site/docs/concepts/shadow-before-cutover.md`.
- A new **PHP API** surface (public class/method/signature) → update `docs-site/docs/reference/php-api.md`.
- A substantive **README** change (features, quick-start, ecosystem) → reflect it in the relevant page(s).

A **new page** MUST also be registered in `navigation[]` in `docmd.config.json`, or it will not appear in the
sidebar.

## When it does NOT apply (state it explicitly in the PR/changelog)

Internal refactors with no behavior change, test-only changes, tooling/CI fixes, or pure cosmetics. If you
skip a docs update, say so and why in the PR description or changelog.

## Definition of done (blocking)

1. The matching `docs-site/docs/**` page(s) reflect reality — real class/command/config names, the exact
   shadow semantics (`Gate::after` returning `null`, the direct `hasPermissionTo` probe, deny-overrides), and
   the real `laravel-iam.manifest.v2` fields the bridge emits.
2. New pages are in `navigation[]`.
3. From `docs-site/`: **`npm run check` and `npm run build` are green**, and `_site/index.html` exists.

## Anti-patterns (reject in review)

- A user-facing feature shipped with no docs-site update.
- A page added but missing from `navigation[]`.
- MDX/JSX or raw HTML tags, or `::: button` (the guard fails the build).
- Documenting a colon-style decisions endpoint instead of the `/api/iam/v1/decisions/check` slash form, or
  claiming the bridge enforces (enforcement is the client's Gate adapter; the bridge only observes in shadow).
- Inventing classes/methods/config that don't exist — accuracy is non-negotiable.
