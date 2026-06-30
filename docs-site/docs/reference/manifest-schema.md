---
title: Manifest schema
description: Field-by-field reference for the laravel-iam.manifest.v2 document the bridge generates — the app block, permissions[], roles[], the values the bridge sets, and how it relates to the server schema.
---

# Manifest schema

This is the field reference for the `laravel-iam.manifest.v2` document produced by
[`iam:spatie:manifest`](/reference/cli#iam-spatie-manifest) / `ManifestGenerator`. The authoritative schema
is owned by [`laravel-iam-server`](https://doc.laravel-iam-server.padosoft.com); this page documents exactly
what the **bridge** emits.

## Shape

```json
{
  "schema": "laravel-iam.manifest.v2",
  "app": {
    "key": "billing",
    "name": "Billing",
    "type": "laravel",
    "risk_level": "low"
  },
  "permissions": [
    { "key": "orders.refund", "risk": "high" },
    { "key": "manage_users", "risk": "low" }
  ],
  "roles": [
    { "key": "admin", "permissions": ["orders.refund", "manage_users"] },
    { "key": "viewer", "permissions": [] }
  ]
}
```

## Top level

| Field | Type | Value the bridge sets |
|---|---|---|
| `schema` | string | always `"laravel-iam.manifest.v2"` |
| `app` | object | the application block (below) |
| `permissions` | array | unique, slugged permissions with risk |
| `roles` | array | roles referencing surviving permission keys |

## `app`

| Field | Type | Source / default |
|---|---|---|
| `key` | string | `--app` option; falls back to `legacy` if blank |
| `name` | string | `--name` option; defaults to `app.key` |
| `type` | string | constant `"laravel"` |
| `risk_level` | string | constant `"low"` |

::: callout info "`type` and `risk_level` are constants here"
The generator does not infer `app.type` or `app.risk_level`; it always emits `laravel` / `low`. Adjust them
on the server side if your application warrants a different classification.
:::

## `permissions[]`

Each entry:

| Field | Type | Meaning |
|---|---|---|
| `key` | string | slugged IAM key, matches `^[a-z][a-z0-9_.-]*$` |
| `risk` | string | `high` or `low`, from `PermissionMapper::inferRisk()` |

Rules the bridge guarantees:

- **Unique** — colliding slugs are deduplicated (first wins).
- **Valid** — every key passes `PermissionMapper::toKey()`.
- `risk` is a **starting heuristic** (last `.`-segment vs the high-impact set), to be reviewed.

## `roles[]`

Each entry:

| Field | Type | Meaning |
|---|---|---|
| `key` | string | slugged role key |
| `permissions` | string[] | slugged permission keys that **exist** in `permissions[]` |

Rules:

- **No dangling references** — a role only lists keys present in the top-level `permissions[]`.
- **No duplicates within a role** — each key appears at most once.
- Empty roles carry over with `"permissions": []`.

## The slug grammar

$$
\text{key} \in [a\text{-}z]\,[a\text{-}z0\text{-}9\_.\text{-}]^{*}
$$

Every `key` (permission and role) satisfies this. See [permission slugging](/concepts/permission-slugging).

## Validate before you trust it

The bridge guarantees internal consistency, not semantic correctness. Run the server validator and review the
proposal before registering:

```bash
php artisan iam:manifest:validate storage/app/iam/iam.manifest.json
php artisan iam:app:register      storage/app/iam/iam.manifest.json
```

::: callout warning "What the bridge does NOT put in the manifest"
- **Direct user permissions** from the scan are not turned into roles — handle them explicitly.
- **Guards** are not encoded; map each guard to its own application (`--app`) and generate a manifest per
  guard.
- **Conditions/scopes** (ABAC/ReBAC) are not inferred — apps declare those on the server, never the bridge.
:::

## Next

- [Manifest contract](/architecture/manifest-contract) — how the generator builds this document.
- [Manifest generation](/guides/manifest-generation) — running the command.
