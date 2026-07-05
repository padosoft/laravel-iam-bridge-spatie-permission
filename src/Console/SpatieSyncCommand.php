<?php

declare(strict_types=1);

namespace Padosoft\Iam\Bridge\Spatie\Console;

use Illuminate\Console\Command;
use Padosoft\Iam\Bridge\Spatie\Migration\ManifestGenerator;
use Padosoft\Iam\Bridge\Spatie\Migration\SpatieScanner;
use Padosoft\Iam\Domain\Applications\Manifest\ManifestRegistry;

/**
 * Regenerate the IAM manifest from the CURRENT spatie/laravel-permission inventory and **sync it to IAM** in
 * one shot — the automated alternative to hand-editing + re-submitting a manifest every time roles/permissions
 * change. Submitting computes the diff vs the applied state; additive low-risk changes apply straight away,
 * a **removal is a breaking change** that needs approval. Removed roles/permissions are **deprecated** (kept
 * for history, disabled) — re-adding them re-activates. Schedule it (e.g. hourly) for hands-off drift-sync.
 */
final class SpatieSyncCommand extends Command
{
    protected $signature = 'iam:spatie:sync
        {--app=legacy : the manifest app.key}
        {--name= : app.name (default = app.key)}
        {--approve : approve gated (breaking) changes and apply immediately}
        {--dry-run : show the diff and exit without changing IAM}';

    protected $description = 'Regenerate the IAM manifest from spatie/laravel-permission and sync it to IAM (diff → apply, or submit for approval).';

    public function handle(SpatieScanner $scanner, ManifestGenerator $generator, ManifestRegistry $registry): int
    {
        $appKey = is_string($this->option('app')) && $this->option('app') !== '' ? (string) $this->option('app') : 'legacy';
        $name = is_string($this->option('name')) && $this->option('name') !== '' ? (string) $this->option('name') : $appKey;

        $payload = $generator->generate($scanner->scan(), ['key' => $appKey, 'name' => $name]);
        $manifest = $registry->submit($payload, 'spatie-sync');

        if ($manifest->status === 'rejected') {
            $this->error('Generated manifest is invalid: '.implode('; ', array_map('strval', (array) $manifest->validation_errors)));

            return self::FAILURE;
        }

        $this->renderDiff(is_array($manifest->diff) ? $manifest->diff : []);

        if ($this->option('dry-run') === true) {
            $registry->reject($manifest, 'spatie-sync (dry-run)');
            $this->info('Dry run — nothing applied (the pending manifest was discarded).');

            return self::SUCCESS;
        }

        // Additive low-risk changes come back already 'approved'; a removal is gated as 'pending_approval'.
        if ($manifest->status === 'approved') {
            $registry->apply($manifest);
            $this->info('Applied. Removed roles/permissions are DEPRECATED (kept for history, disabled); re-adding re-activates them.');
        } elseif ($this->option('approve') === true) {
            $registry->approve($manifest, 'spatie-sync');
            $registry->apply($manifest);
            $this->info('Approved + applied. Removed roles/permissions are DEPRECATED (kept for history, disabled).');
        } else {
            $this->warn('This change removes a role/permission (breaking) → it needs approval. Submitted as PENDING — approve it in the console, or re-run with --approve.');
        }

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $diff */
    private function renderDiff(array $diff): void
    {
        $perm = is_array($diff['permissions'] ?? null) ? $diff['permissions'] : [];
        $role = is_array($diff['roles'] ?? null) ? $diff['roles'] : [];
        $count = static fn (mixed $v): int => is_array($v) ? count($v) : 0;

        $this->line('Diff vs the applied manifest:');
        $this->line('  permissions  +'.$count($perm['added'] ?? null).' / -'.$count($perm['removed'] ?? null).'  (removed → deprecated)');
        $this->line('  roles        +'.$count($role['added'] ?? null).' / -'.$count($role['removed'] ?? null).'  (removed → deprecated)');
        if (($diff['breaking'] ?? false) === true) {
            $this->warn('  breaking change (a removal) → approval required');
        }
    }
}
