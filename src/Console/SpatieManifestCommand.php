<?php

declare(strict_types=1);

namespace Padosoft\Iam\Bridge\Spatie\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Padosoft\Iam\Bridge\Spatie\Migration\ManifestGenerator;
use Padosoft\Iam\Bridge\Spatie\Migration\SpatieScanner;

/**
 * `iam:spatie:manifest` (doc 07 §7) — genera un manifest `laravel-iam.manifest.v2` dall'inventory
 * Spatie, pronto per `iam:manifest:validate` e `iam:app:register`. Il manifest è una PROPOSTA: risk
 * e step-up sono euristiche da rivedere; in produzione va approvato prima del sync.
 */
final class SpatieManifestCommand extends Command
{
    protected $signature = 'iam:spatie:manifest
        {--app=legacy : app.key del manifest}
        {--name= : app.name (default = app.key)}
        {--output=storage/app/iam/iam.manifest.json : File di output}';

    protected $description = 'Genera un manifest IAM dall\'inventory di spatie/laravel-permission';

    public function handle(SpatieScanner $scanner, ManifestGenerator $generator, Filesystem $files): int
    {
        $appKey = $this->stringOption('app') ?? 'legacy';
        $name = $this->stringOption('name') ?? $appKey;

        $manifest = $generator->generate($scanner->scan(), ['key' => $appKey, 'name' => $name]);

        $output = $this->stringOption('output') ?? 'storage/app/iam/iam.manifest.json';
        $files->ensureDirectoryExists(dirname($output));
        $files->put($output, (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $permissions = is_array($manifest['permissions']) ? $manifest['permissions'] : [];
        $roles = is_array($manifest['roles']) ? $manifest['roles'] : [];
        $this->info('Manifest generato ('.count($permissions).' permessi, '.count($roles).' ruoli) → '.$output);
        $this->line('Prossimo: php artisan iam:manifest:validate '.$output);

        return self::SUCCESS;
    }

    private function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
