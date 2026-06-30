---
title: Naming & mapping hygiene
description: How to keep Spatie names clean so they slug to good IAM keys — avoiding collisions, handling guards and direct grants, and re-rating risk before you register the manifest.
---

# Naming & mapping hygiene

Garbage in, garbage out: the manifest is only as clean as the Spatie names it is generated from. Fixing
naming **at the source** (in Spatie, before the scan) is cheaper than untangling collisions in the manifest.

## Why it matters

[Slugging](/concepts/permission-slugging) is deterministic but **lossy** — distinct names can collapse to the
same key. Two different permissions sharing a key become one in the manifest (the generator keeps the first),
which silently *changes* your authorization model. Clean names prevent that.

## Avoid collisions before they happen

`toKey()` lowercases, replaces illegal runs with `_`, collapses repeats, and trims separators. Names that
differ only by case, spacing, or punctuation converge:

| These names… | …all slug to |
|---|---|
| `Manage Users`, `manage   users`, `manage_users` | `manage_users` |
| `Orders/Refund`, `orders refund`, `orders__refund` | `orders_refund` |

::: callout tip "Pick one convention and apply it in Spatie"
Decide on a single style — dotted `domain.action` (`orders.refund`, `users.export`) is the most IAM-friendly
because it slugs unchanged and feeds the risk heuristic (which reads the last `.`-segment). Rename Spatie
permissions to that convention **before** the final scan.
:::

## Handle the structural smells

| Smell (from `report.md`) | What to do |
|---|---|
| **Empty roles** | Drop them in Spatie, or accept them as empty roles in the manifest deliberately. |
| **Semantic duplicates** | Rename so the two permissions slug to **different** keys, then re-scan. |
| **Direct user permissions** | Decide each one: fold into a role, or plan an explicit IAM grant. They are *not* auto-mapped to roles. |
| **Multiple guards** | Map each guard to its own IAM application (`--app`); migrate per guard/app. |

## Re-rate risk by hand

`inferRisk()` only flags a fixed set of high-impact action words on the **last `.`-segment**:

```text
refund, delete, destroy, drop, truncate, grant, revoke,
impersonate, export, approve, disable, suspend, wipe
```

Anything else defaults to `low`. So:

- `billing.settle` → `low` (the heuristic doesn't know `settle`) — **probably wrong**, re-rate to `high`.
- `users.manage` → `low` (no `.` action word match) — re-rate if "manage" is powerful in your app.
- `orders.refund` → `high` — correct, leave it.

Review every permission you consider sensitive before `iam:app:register`; the heuristic is a starting point,
not an auditor.

## Worked example

A Spatie install has `Refund Orders`, `orders.refund`, and `report-export`:

::: steps
1. **Spot the collision risk.** `Refund Orders` → `refund_orders`; `orders.refund` → `orders.refund`. These
   *don't* collide, but they are two names for one idea — consolidate in Spatie to a single
   `orders.refund`.

2. **Fix the risk-blind name.** `report-export` → `report-export` (slug ok) but the action segment is
   `report-export`, not `export`, so it infers `low`. Rename to `reports.export` so it infers `high`.

3. **Re-scan and regenerate.** Run `iam:spatie:scan` then `iam:spatie:manifest`; confirm the manifest now has
   one `orders.refund` (`high`) and `reports.export` (`high`).
:::

::: callout warning "Gotchas"
- A name already containing `:` is treated as a fully-qualified `full_key` and **passes through** slugging —
  make sure that is intended, not an accident of naming.
- Renaming in Spatie after cutover is the wrong layer — once IAM is the authority, manage keys in the
  manifest/server, not in Spatie.
- The dedup "keep first" order follows the scan order; don't rely on it to pick the "right" duplicate — fix
  the names instead.
:::

## Next

- [Permission slugging](/concepts/permission-slugging) — the exact algorithm.
- [Manifest generation](/guides/manifest-generation) — where these names become keys.
