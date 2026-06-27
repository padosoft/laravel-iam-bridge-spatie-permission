<?php

declare(strict_types=1);

namespace Padosoft\Iam\Bridge\Spatie\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Padosoft\Iam\Bridge\Spatie\Migration\SpatieScanner;

/**
 * `iam:spatie:scan` (doc 07 §5) — inventory automatico di spatie/laravel-permission. Solo lettura:
 * scrive `inventory.json` + `report.md` con i conteggi e gli smell (ruoli vuoti, permessi orfani,
 * permessi diretti, guard multipli) sotto la dir di output. Primo passo del cutover.
 */
final class SpatieScanCommand extends Command
{
    protected $signature = 'iam:spatie:scan {--output=storage/app/iam/spatie-inventory : Cartella di output}';

    protected $description = 'Inventory (read-only) di spatie/laravel-permission per la migrazione a IAM';

    public function handle(SpatieScanner $scanner, Filesystem $files): int
    {
        $scan = $scanner->scan();

        $dirOption = $this->option('output');
        $dir = is_string($dirOption) && $dirOption !== '' ? $dirOption : 'storage/app/iam/spatie-inventory';
        $files->ensureDirectoryExists($dir);

        $files->put($dir.'/inventory.json', (string) json_encode($scan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $files->put($dir.'/report.md', $this->report($scan));

        $permCount = is_array($scan['permissions'] ?? null) ? count($scan['permissions']) : 0;
        $roleCount = is_array($scan['roles'] ?? null) ? count($scan['roles']) : 0;
        $this->info("Inventory Spatie: {$roleCount} ruoli, {$permCount} permessi → {$dir}");

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $scan */
    private function report(array $scan): string
    {
        $roles = is_array($scan['roles'] ?? null) ? $scan['roles'] : [];
        $permissions = is_array($scan['permissions'] ?? null) ? $scan['permissions'] : [];
        $guards = is_array($scan['guards'] ?? null) ? $scan['guards'] : [];
        $direct = is_array($scan['direct_user_permissions'] ?? null) ? $scan['direct_user_permissions'] : [];

        $emptyRoles = array_filter($roles, static fn ($r): bool => is_array($r) && (is_array($r['permissions'] ?? null) ? $r['permissions'] : []) === []);

        $lines = [
            '# Spatie → IAM — Report di inventory',
            '',
            '- Ruoli: '.count($roles),
            '- Permessi: '.count($permissions),
            '- Ruoli senza permessi: '.count($emptyRoles),
            '- Permessi diretti utente: '.count($direct),
            '- Guard distinti: '.count($guards).(count($guards) > 1 ? ' ⚠️ guard multipli' : ''),
            '',
            'Rivedere a mano: naming incoerente, permessi duplicati semanticamente, permessi critical.',
        ];

        return implode("\n", $lines)."\n";
    }
}
