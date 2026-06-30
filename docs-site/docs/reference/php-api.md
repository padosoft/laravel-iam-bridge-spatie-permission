---
title: PHP API
description: The public classes and methods of the bridge — SpatieScanner, PermissionMapper, ManifestGenerator, ShadowGate, RecordsMismatch / MismatchRecorder, and the service provider — with exact signatures.
---

# PHP API

Every class lives under the `Padosoft\Iam\Bridge\Spatie\` namespace, declares `strict_types=1`, and is
`final`. Signatures below match the source exactly.

## `Migration\SpatieScanner`

Read-only inventory of the Spatie tables.

```php
final class SpatieScanner
{
    /** @param array<string, string> $tables override table names (default = standard Spatie) */
    public function __construct(ConnectionInterface $db, array $tables = []);

    /** @return array<string, mixed> */
    public function scan(): array;
}
```

`scan()` returns an array with keys: `permissions` (`[{ name, guard }]`), `roles`
(`[{ name, guard, permissions[] }]`), `direct_user_permissions` (`[{ permission, model_type, model_id }]`),
`model_has_roles_count` (int), `guards` (`string[]`). It issues only `SELECT`s. The service provider builds
it with table names from `permission.table_names`.

## `Migration\PermissionMapper`

Deterministic slugging + risk heuristic.

```php
final class PermissionMapper
{
    /** Slug a Spatie name to a valid IAM key (^[a-z][a-z0-9_.-]*$). Deterministic, idempotent, total. */
    public function toKey(string $name): string;

    /** IAM full_key: "<application>:<key>". Names already containing ':' pass through. */
    public function toFullKey(string $application, string $name): string;

    /** 'high' if the last '.'-segment is a high-impact action, else 'low'. */
    public function inferRisk(string $key): string;
}
```

High-risk action set: `refund, delete, destroy, drop, truncate, grant, revoke, impersonate, export, approve,
disable, suspend, wipe`. See [permission slugging](/concepts/permission-slugging).

## `Migration\ManifestGenerator`

Inventory → `laravel-iam.manifest.v2`.

```php
final class ManifestGenerator
{
    public function __construct(PermissionMapper $mapper);

    /**
     * @param array<string, mixed> $scan output of SpatieScanner::scan()
     * @param array<string, mixed> $app  app metadata: key (required), name, type, risk_level
     * @return array<string, mixed>
     */
    public function generate(array $scan, array $app): array;
}
```

Slugs and **deduplicates** permission keys (keeps the first colliding key), assigns a starting `risk`, and
emits roles whose `permissions[]` reference only surviving keys. See
[manifest contract](/architecture/manifest-contract).

## `Shadow\ShadowGate`

The `Gate::after` observer.

```php
final class ShadowGate
{
    public function __construct(
        IamClient $client,
        RecordsMismatch $recorder,
        PermissionMapper $mapper,
        string $application,
    );

    /** Hook Gate::after; the callback always returns null (never alters the outcome). */
    public function register(Gate $gate): void;

    /**
     * Evaluate IAM, probe Spatie directly, record a mismatch on divergence.
     * @param array<array-key, mixed> $arguments
     */
    public function compare(
        Authenticatable $user,
        string $ability,
        ?bool $localResult,
        array $arguments = [],
    ): void;
}
```

`compare()` is public for direct unit testing. See [ShadowGate internals](/architecture/shadow-gate).

## `Shadow\RecordsMismatch` (interface)

The pluggable mismatch sink.

```php
interface RecordsMismatch
{
    public function record(string $subjectId, string $ability, bool $spatieAllows, bool $iamAllows): void;
}
```

## `Shadow\MismatchRecorder`

The default sink — structured log.

```php
final class MismatchRecorder implements RecordsMismatch
{
    public function __construct(LoggerInterface $logger);

    /** Logs a `iam.shadow.mismatch` warning with subject_id, ability, spatie/iam allows, direction. */
    public function record(string $subjectId, string $ability, bool $spatieAllows, bool $iamAllows): void;
}
```

Bind your own `RecordsMismatch` to redirect divergences — see [observability](/operations/observability).

## `IamSpatieBridgeServiceProvider`

Wiring (extends `Spatie\LaravelPackageTools\PackageServiceProvider`).

```php
final class IamSpatieBridgeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void;  // name, config file, commands
    public function packageRegistered(): void;                 // singleton bindings
    public function packageBooted(): void;                     // if mode==shadow → ShadowGate->register(Gate)
}
```

Registers the commands, binds `PermissionMapper`, `SpatieScanner`, `ManifestGenerator`,
`RecordsMismatch`/`MismatchRecorder`, and `ShadowGate` as singletons, and — only in `mode=shadow` — hooks the
`ShadowGate` onto the application `Gate`.

::: callout info "Service container bindings"
| Abstract | Concrete |
|---|---|
| `PermissionMapper` | self (singleton) |
| `SpatieScanner` | built with the default DB connection + `permission.table_names` |
| `ManifestGenerator` | self (singleton) |
| `RecordsMismatch` | `MismatchRecorder` on `log->channel(mismatch_log_channel)` |
| `ShadowGate` | built with `IamClient`, `RecordsMismatch`, `PermissionMapper`, `application` |
:::

## Next

- [CLI reference](/reference/cli) — the commands that drive these classes.
- [Manifest schema](/reference/manifest-schema) — the generator's output contract.
