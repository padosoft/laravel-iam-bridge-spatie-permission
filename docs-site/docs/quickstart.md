---
title: Quickstart
description: Install the bridge, publish config, run a read-only scan, generate the manifest, observe in shadow, and cut over — the whole loop in one page.
---

# Quickstart

This page walks the full loop end to end. The bridge ships in **shadow mode by default**
(`IAM_SPATIE_MODE=shadow`), so installing it changes **no** authorization behavior.

::: callout info "Prerequisites"
PHP **8.3+**, Laravel **13+**, an existing `spatie/laravel-permission` (v6) install, and a reachable Laravel
IAM server wired via [`padosoft/laravel-iam-client`](https://doc.laravel-iam-client.padosoft.com). See
[Installation](/installation) for the details.
:::

## 1. Install

```bash
composer require padosoft/laravel-iam-bridge-spatie-permission
php artisan vendor:publish --tag=iam-spatie-config
```

This publishes `config/iam-spatie.php`. Nothing else changes yet — the `ShadowGate` only registers when
`mode` is `shadow` (the default), and in shadow it never alters a decision.

## 2. Inventory your current setup (read-only)

```bash
php artisan iam:spatie:scan --output=storage/app/iam/spatie-inventory
```

This writes `inventory.json` and a human-readable `report.md` summarizing roles, permissions, empty roles,
direct user grants, and distinct guards. It **touches nothing** in your database. Open `report.md` and clean
up the smells (inconsistent naming, semantic duplicates, critical permissions).

## 3. Generate the IAM manifest

```bash
php artisan iam:spatie:manifest --app=billing --name="Billing" \
  --output=storage/app/iam/iam.manifest.json
```

The manifest is a **proposal**: review the inferred `risk` levels and the role → permission sets, then
validate and register it on the server (these commands live in `laravel-iam-server`):

```bash
php artisan iam:manifest:validate storage/app/iam/iam.manifest.json
php artisan iam:app:register      storage/app/iam/iam.manifest.json
```

## 4. Observe in shadow

With `IAM_SPATIE_MODE=shadow`, the `ShadowGate` is registered automatically. Every `Gate` check is mirrored
to IAM and divergences are logged as `iam.shadow.mismatch`:

```php
// Your existing code — unchanged. Spatie still decides.
Gate::authorize('orders.refund', $order);
// In the background: IAM evaluates billing:orders.refund and logs ONLY if it disagrees with Spatie.
```

Point the mismatch channel wherever your reviewers look:

```dotenv
IAM_SPATIE_MODE=shadow
IAM_SPATIE_APP=billing
IAM_SPATIE_MISMATCH_CHANNEL=iam-shadow
```

Run real production traffic and watch that channel.

## 5. Review mismatches and cut over

When the mismatch log is clean (or every entry is explained), flip the mode:

```dotenv
IAM_SPATIE_MODE=enforce
```

IAM is now the authority (enforcement comes from the client's Gate adapter); Spatie becomes a read-only
cache. **Rollback** is the same switch in reverse — set `IAM_SPATIE_MODE=shadow` and you are back to Spatie
deciding, instantly.

::: callout success "What you just did"
You proved decision parity on real traffic before changing who decides, and you kept an instant escape hatch.
That is the whole point of the bridge.
:::

## Next

- [Core concepts](/core-concepts) — the mental model behind each step.
- [Guides → Inventory & scan](/guides/inventory-and-scan) — the scan in depth.
- [Guides → Cutover & rollback](/guides/cutover-and-rollback) — the reversible switch.
