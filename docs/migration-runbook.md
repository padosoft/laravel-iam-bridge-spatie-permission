---
title: Migration runbook
description: The full step-by-step flow — inventory, manifest, shadow observation, cutover to enforce, and rollback.
---

# Migration runbook

The complete, reversible path from `spatie/laravel-permission` to Laravel IAM. Each step is safe to repeat.

::: steps
1. **Inventory (read-only)**
   Produce the inventory and the review report. Touches nothing in your DB.
   ```bash
   php artisan iam:spatie:scan --output=storage/app/iam/spatie-inventory
   ```
   Open `report.md` and address the smells: empty roles, orphan permissions, direct user grants, multiple
   guards, inconsistent naming, semantic duplicates.

2. **Generate the manifest**
   Map the inventory to a `laravel-iam.manifest.v2`. The manifest is a **proposal** — review the inferred
   `risk` levels and role→permission sets.
   ```bash
   php artisan iam:spatie:manifest --app=billing --name="Billing" \
     --output=storage/app/iam/iam.manifest.json
   ```

3. **Validate & register on the server**
   ```bash
   php artisan iam:manifest:validate storage/app/iam/iam.manifest.json
   php artisan iam:app:register      storage/app/iam/iam.manifest.json
   ```

4. **Observe in shadow**
   Keep `IAM_SPATIE_MODE=shadow`. Spatie decides for real; `ShadowGate` evaluates IAM in parallel and logs
   only divergences as `iam.shadow.mismatch`.
   ```dotenv
   IAM_SPATIE_MODE=shadow
   IAM_SPATIE_APP=billing
   IAM_SPATIE_MISMATCH_CHANNEL=iam-shadow
   ```
   Run real production traffic. Watch the mismatch channel.

5. **Review mismatches until clean**
   Each entry is either a mapping bug (fix the manifest, re-register) or an intended change (document it).
   Do not proceed until the diff is clean or every divergence is explained.

6. **Cut over to enforce**
   Flip the mode. IAM becomes the authority (enforcement via the client's Gate adapter); Spatie becomes a
   read-only cache (`write_protection`).
   ```dotenv
   IAM_SPATIE_MODE=enforce
   ```

7. **Rollback (anytime)**
   The cutover is a single switch. To revert instantly:
   ```dotenv
   IAM_SPATIE_MODE=shadow
   ```
   Spatie is deciding again, exactly as before.
:::

::: callout warning "Cut over per application"
In a fleet, migrate one app at a time. Each application gets its own manifest and its own
`IAM_SPATIE_APP` prefix, so you can prove parity and cut over independently.
:::
