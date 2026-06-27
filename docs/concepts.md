---
title: Concepts
description: The mental model behind the bridge — inventory, manifest generation, shadow diffing, deny-overrides, reversible cutover.
---

# Concepts

## The problem

`spatie/laravel-permission` stores roles and permissions in your app's database and answers
`Gate`/`can()` checks locally. Moving authorization to an external control plane (Laravel IAM) means a
*different* system will start answering those checks. Flip it blindly on a live app and a single bad mapping
either locks people out or — worse — lets them in. You need a way to **prove the two systems agree** before
you trust the new one.

## Mental model

Migration is an **observation problem before it is a switch**. Run both authorities side by side, watch
where they disagree, fix the mappings, and only then change who decides.

```
            ┌─────────────┐        scan (read-only)        ┌──────────────────┐
  Spatie ──▶│ SpatieScanner│ ─────────────────────────────▶│ inventory.json    │
  tables    └─────────────┘                                 │ + report.md       │
                                                            └────────┬─────────┘
                                                ManifestGenerator     │
                                                                      ▼
                                                        laravel-iam.manifest.v2
                                                                      │ register on server
                                                                      ▼
  Gate check ──▶ Spatie decides (REAL) ──▶ ShadowGate (Gate::after) ──▶ IAM decides (parallel)
                                                  │ compare
                                                  ▼
                                       mismatch? → RecordsMismatch (log/queue)
```

## Core entities

- **`SpatieScanner`** — read-only inventory of the Spatie tables (roles, permissions, role↔permission,
  direct user grants, guards).
- **`PermissionMapper`** — deterministic, idempotent slugging of Spatie names → IAM keys
  (`^[a-z][a-z0-9_.-]*$`), plus a `risk` heuristic for high-impact actions.
- **`ManifestGenerator`** — turns the inventory into a `laravel-iam.manifest.v2`, deduping keys that collide
  (semantic duplicates to review).
- **`ShadowGate`** — a `Gate::after` hook that compares IAM vs Spatie and returns `null` (never alters the
  live result).
- **`RecordsMismatch` / `MismatchRecorder`** — the pluggable sink for divergences.

## Example

In shadow, your code is unchanged:

```php
Gate::authorize('orders.refund', $order);  // Spatie decides, as always
```

Behind the scenes `ShadowGate` evaluates `billing:orders.refund` on IAM and, only if it disagrees with
Spatie, emits:

```
iam.shadow.mismatch  { subject_id, ability, spatie_allows, iam_allows, direction }
```

## Anti-patterns

::: callout danger "Don't trust the Gate::after result as 'Spatie's answer'"
The `?bool $result` passed to `Gate::after` may have been short-circuited by another `Gate::before` (for
example the IAM client's own enforcement). Comparing against it would compare **IAM with IAM** and produce a
false-zero mismatch — a clean diff on invalid data. The bridge probes Spatie directly via
`hasPermissionTo` instead.
:::

- **Don't cut over with unexplained mismatches.** Every divergence is either a mapping bug to fix or an
  intended change to document.
- **Don't hardcode permissions in the core.** Apps declare them via the generated manifest.
- **Don't treat the manifest's `risk` as truth.** It's a starting heuristic for human review.

## Why this design

Because authorization is the one thing you cannot get wrong silently. Deny-overrides diffing, a read-only
scanner, a shadow phase that changes nothing, and an env-var cutover make the migration **observable and
reversible** — you can always go back to the system that was working a second ago.
