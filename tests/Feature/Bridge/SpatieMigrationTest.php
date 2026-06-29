<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Padosoft\Iam\Bridge\Spatie\Migration\ManifestGenerator;
use Padosoft\Iam\Bridge\Spatie\Migration\PermissionMapper;
use Padosoft\Iam\Bridge\Spatie\Migration\SpatieScanner;
use Padosoft\Iam\Domain\Applications\Manifest\ManifestValidator;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['permissions', 'roles'] as $table) {
        Schema::create($table, function ($t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->string('guard_name');
        });
    }
    Schema::create('role_has_permissions', function ($t) {
        $t->unsignedBigInteger('permission_id');
        $t->unsignedBigInteger('role_id');
    });
    Schema::create('model_has_permissions', function ($t) {
        $t->unsignedBigInteger('permission_id');
        $t->string('model_type');
        $t->string('model_id');
    });
    Schema::create('model_has_roles', function ($t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->string('model_id');
    });

    DB::table('permissions')->insert([
        ['id' => 1, 'name' => 'orders.refund', 'guard_name' => 'web'],
        ['id' => 2, 'name' => 'orders.view', 'guard_name' => 'web'],
        ['id' => 3, 'name' => 'Manage Users', 'guard_name' => 'web'],
    ]);
    DB::table('roles')->insert([
        ['id' => 1, 'name' => 'order_manager', 'guard_name' => 'web'],
        ['id' => 2, 'name' => 'viewer', 'guard_name' => 'web'],
    ]);
    DB::table('role_has_permissions')->insert([
        ['role_id' => 1, 'permission_id' => 1],
        ['role_id' => 1, 'permission_id' => 2],
    ]);
    DB::table('model_has_permissions')->insert([
        ['permission_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => '7'],
    ]);
});

it('PermissionMapper slugifica nomi Spatie in chiavi IAM valide', function () {
    $m = new PermissionMapper;

    expect($m->toKey('orders.refund'))->toBe('orders.refund')
        ->and($m->toKey('Manage Users'))->toBe('manage_users')
        ->and($m->toKey('123abc'))->toBe('p_123abc')   // deve iniziare con [a-z]
        ->and($m->inferRisk('orders.refund'))->toBe('high')
        ->and($m->inferRisk('orders.view'))->toBe('low');
});

it('SpatieScanner produce l\'inventory di ruoli, permessi, diretti e guard', function () {
    $scan = (new SpatieScanner(DB::connection()))->scan();

    expect($scan['permissions'])->toHaveCount(3)
        ->and($scan['roles'])->toHaveCount(2)
        ->and($scan['guards'])->toBe(['web'])
        ->and($scan['direct_user_permissions'])->toHaveCount(1);

    $orderManager = collect($scan['roles'])->firstWhere('name', 'order_manager');
    expect($orderManager['permissions'])->toContain('orders.refund', 'orders.view');
});

it('ManifestGenerator genera un manifest laravel-iam.manifest.v2 VALIDO', function () {
    $scan = (new SpatieScanner(DB::connection()))->scan();
    $manifest = (new ManifestGenerator(new PermissionMapper))->generate($scan, ['key' => 'legacy-admin', 'name' => 'Legacy Admin']);

    expect($manifest['schema'])->toBe('laravel-iam.manifest.v2')
        ->and($manifest['app']['key'])->toBe('legacy-admin');

    $refund = collect($manifest['permissions'])->firstWhere('key', 'orders.refund');
    expect($refund['risk'])->toBe('high');

    $orderManager = collect($manifest['roles'])->firstWhere('key', 'order_manager');
    expect($orderManager['permissions'])->toContain('orders.refund', 'orders.view');

    // Il manifest generato deve passare il validatore del server (coerenza referenziale inclusa).
    expect((new ManifestValidator)->validate($manifest)->valid)->toBeTrue();
});
