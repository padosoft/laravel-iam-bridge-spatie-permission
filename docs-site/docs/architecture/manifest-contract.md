---
title: Manifest contract
description: The laravel-iam.manifest.v2 document the bridge generates — its structure, how ManifestGenerator maps the inventory into it, the dedup rule, and how roles reference surviving permission keys.
---

# Manifest contract

The bridge's output is a `laravel-iam.manifest.v2` document — the declarative contract the IAM server
validates and registers. This page specifies the document the bridge produces and how `ManifestGenerator`
builds it.

## The document

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

| Field | Type | Source |
|---|---|---|
| `schema` | string | constant `"laravel-iam.manifest.v2"` |
| `app.key` | string | `--app` option (default `legacy`; falls back to `legacy` if blank) |
| `app.name` | string | `--name` option (default = `app.key`) |
| `app.type` | string | constant `"laravel"` |
| `app.risk_level` | string | constant `"low"` |
| `permissions[]` | `{ key, risk }` | one per **surviving** slugged Spatie permission |
| `roles[]` | `{ key, permissions[] }` | one per Spatie role; `permissions[]` are surviving keys |

## How the generator maps the inventory

```php
// ManifestGenerator::generate($scan, $app)
```

```mermaid
flowchart TB
    SCAN["scan.permissions[]"] --> P1["for each name"]
    P1 --> P2["key = mapper.toKey(name)"]
    P2 --> P3{seen[key]?}
    P3 -->|yes| DROP["drop (semantic duplicate)"]
    P3 -->|no| KEEP["permissions[] += { key, risk: inferRisk(key) }; seen[key]=true"]
    SCAN2["scan.roles[]"] --> R1["for each role"]
    R1 --> R2["permKeys = role.permissions slugged AND present in seen"]
    R2 --> R3["roles[] += { key: toKey(role.name), permissions: permKeys }"]
```

### Permissions: slug, dedup, risk

```php
$key = $this->mapper->toKey($name);
if (isset($seen[$key])) {
    continue; // semantic duplicate → keep the first
}
$seen[$key] = true;
$permissions[] = ['key' => $key, 'risk' => $this->mapper->inferRisk($key)];
```

Each permission name is slugged; the **first** occurrence of a key wins and later collisions are dropped (a
[semantic duplicate](/concepts/permission-slugging#collisions-are-semantic-duplicates) to review). `risk`
comes from `inferRisk()`.

### Roles reference only surviving keys

```php
foreach ($role['permissions'] as $permName) {
    $mapped = $this->mapper->toKey($permName);
    if (isset($seen[$mapped]) && !in_array($mapped, $permKeys, true)) {
        $permKeys[] = $mapped; // only keys that exist as real permissions
    }
}
$roles[] = ['key' => $this->mapper->toKey($role['name']), 'permissions' => $permKeys];
```

A role only references a permission key that **survived** as a real permission. A role pointing at a blank or
deduplicated permission never produces a dangling reference — the manifest stays internally consistent.

## Invariants the generator guarantees

- **No dangling role references.** Every key in a role's `permissions[]` exists in the top-level
  `permissions[]`.
- **No duplicate permission keys.** The `seen` set guarantees uniqueness.
- **Every key is valid.** All keys pass through `PermissionMapper::toKey`, so they satisfy
  `^[a-z][a-z0-9_.-]*$`.
- **`app.key` is never empty.** A blank `--app` falls back to `legacy`.

## Validation is the server's job

The generated document is a **proposal**. Structural correctness is enforced by the server's
`iam:manifest:validate` against the `laravel-iam.manifest.v2` schema, and a human approves the semantics
(risk levels, role composition) before `iam:app:register` applies it. The bridge never registers a manifest
itself.

::: collapsible "ADR — generate a consistent proposal, validate on the server"
**Problem.** If the generator produced manifests with dangling references or duplicate keys, validation would
fail downstream and the migration would stall on mechanical errors.

**Decision.** Guarantee internal consistency at generation time (dedup, surviving-key references, valid
slugs), but leave **authority** with the server validator and a human reviewer. The generator proposes; the
server disposes.

**Consequences.** The proposal is always structurally registrable, so review focuses on semantics, not
plumbing. The bridge intentionally does **not** embed the full schema — that lives in
`laravel-iam-server`/`laravel-iam-contracts`, the single source of truth — so the two cannot drift.
:::

::: callout warning "Gotchas"
- `app.type` (`laravel`) and `app.risk_level` (`low`) are **constants** here — set the real values on the
  server side if they differ.
- Deduplication is silent in the JSON; the inventory `report.md` is where collisions are visible.
- Direct user permissions from the scan are **not** turned into roles — they need an explicit decision.
:::

## Next

- [Permission slugging](/concepts/permission-slugging) — how keys are produced.
- [Manifest schema reference](/reference/manifest-schema) — the field-by-field contract.
- [Manifest generation guide](/guides/manifest-generation) — running the command.
