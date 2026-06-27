<?php

declare(strict_types=1);

namespace Padosoft\Iam\Bridge\Spatie;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Padosoft\Iam\Bridge\Spatie\Console\SpatieManifestCommand;
use Padosoft\Iam\Bridge\Spatie\Console\SpatieScanCommand;
use Padosoft\Iam\Bridge\Spatie\Migration\ManifestGenerator;
use Padosoft\Iam\Bridge\Spatie\Migration\PermissionMapper;
use Padosoft\Iam\Bridge\Spatie\Migration\SpatieScanner;
use Padosoft\Iam\Bridge\Spatie\Shadow\MismatchRecorder;
use Padosoft\Iam\Bridge\Spatie\Shadow\RecordsMismatch;
use Padosoft\Iam\Bridge\Spatie\Shadow\ShadowGate;
use Padosoft\Iam\Client\IamClient;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Bridge spatie/laravel-permission → Laravel IAM (doc 07). Registra gli strumenti di migrazione
 * (scan/manifest) e, in modalità SHADOW (default), il `ShadowGate` che osserva i mismatch tra Spatie
 * e IAM senza bloccare nessuno. L'`enforce` lo fornisce il client IAM (Gate adapter del package
 * `-client`): il bridge non scavalca mai le decisioni in shadow.
 */
final class IamSpatieBridgeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-iam-bridge-spatie-permission')
            ->hasConfigFile('iam-spatie')
            ->hasCommands([SpatieScanCommand::class, SpatieManifestCommand::class]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(PermissionMapper::class);

        $this->app->singleton(SpatieScanner::class, fn (Application $app): SpatieScanner => new SpatieScanner(
            $app->make(ConnectionResolverInterface::class)->connection(),
            $this->tableNames(),
        ));

        $this->app->singleton(ManifestGenerator::class);

        $this->app->singleton(RecordsMismatch::class, fn (Application $app): RecordsMismatch => new MismatchRecorder(
            $app->make('log')->channel($this->stringConfig('mismatch_log_channel')),
        ));

        $this->app->singleton(ShadowGate::class, fn (Application $app): ShadowGate => new ShadowGate(
            $app->make(IamClient::class),
            $app->make(RecordsMismatch::class),
            $app->make(PermissionMapper::class),
            $this->stringConfig('application') ?? 'app',
        ));
    }

    public function packageBooted(): void
    {
        // Shadow di default: si OSSERVA. Il passaggio a 'enforce' attiva il Gate adapter del client.
        if ($this->stringConfig('mode') === 'shadow') {
            $this->app->make(ShadowGate::class)->register($this->app->make(Gate::class));
        }
    }

    /** @return array<string, string> */
    private function tableNames(): array
    {
        $names = $this->app->make('config')->get('permission.table_names');
        if (!is_array($names)) {
            return [];
        }

        $out = [];
        foreach (['roles', 'permissions', 'role_has_permissions', 'model_has_permissions', 'model_has_roles'] as $key) {
            if (is_string($names[$key] ?? null) && $names[$key] !== '') {
                $out[$key] = $names[$key];
            }
        }

        return $out;
    }

    private function stringConfig(string $key): ?string
    {
        $value = $this->app->make('config')->get('iam-spatie.'.$key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
