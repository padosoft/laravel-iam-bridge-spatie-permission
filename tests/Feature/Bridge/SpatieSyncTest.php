<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Padosoft\Iam\Domain\Authorization\Models\Permission;
use Padosoft\Iam\Domain\Authorization\Models\Role;

uses(RefreshDatabase::class);

function makeSpatieTables(): void
{
    foreach (['permissions', 'roles'] as $table) {
        if (!Schema::hasTable($table)) {
            Schema::create($table, function ($t) {
                $t->bigIncrements('id');
                $t->string('name');
                $t->string('guard_name');
            });
        }
    }
    if (!Schema::hasTable('role_has_permissions')) {
        Schema::create('role_has_permissions', function ($t) {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
        });
    }
    foreach (['model_has_permissions' => 'permission_id', 'model_has_roles' => 'role_id'] as $table => $col) {
        if (!Schema::hasTable($table)) {
            Schema::create($table, function ($t) use ($col) {
                $t->unsignedBigInteger($col);
                $t->string('model_type');
                $t->string('model_id');
            });
        }
    }
}

it('iam:spatie:sync applies the catalog, then DEPRECATES a removed role and permission (kept, disabled)', function () {
    makeSpatieTables();
    DB::table('permissions')->insert([
        ['id' => 1, 'name' => 'orders.refund', 'guard_name' => 'web'],
        ['id' => 2, 'name' => 'orders.view', 'guard_name' => 'web'],
    ]);
    DB::table('roles')->insert([
        ['id' => 1, 'name' => 'order_manager', 'guard_name' => 'web'],
        ['id' => 2, 'name' => 'viewer', 'guard_name' => 'web'],
    ]);
    DB::table('role_has_permissions')->insert([
        ['role_id' => 1, 'permission_id' => 1],
        ['role_id' => 1, 'permission_id' => 2],
        ['role_id' => 2, 'permission_id' => 2],
    ]);

    // First sync → the catalog is created in IAM.
    test()->artisan('iam:spatie:sync', ['--app' => 'shop', '--approve' => true])->assertExitCode(0);
    expect(Permission::query()->where('full_key', 'shop:orders.view')->exists())->toBeTrue()
        ->and(Role::query()->where('full_key', 'shop:viewer')->exists())->toBeTrue();

    // Remove a permission (orders.view) AND a role (viewer) from spatie…
    DB::table('role_has_permissions')->where('permission_id', 2)->delete();
    DB::table('permissions')->where('id', 2)->delete();
    DB::table('roles')->where('id', 2)->delete();

    // …and re-sync. A removal is breaking → --approve applies it.
    test()->artisan('iam:spatie:sync', ['--app' => 'shop', '--approve' => true])->assertExitCode(0);

    // The removed permission + role are DEPRECATED (kept for history, disabled) — NOT deleted.
    $perm = Permission::query()->where('full_key', 'shop:orders.view')->first();
    $role = Role::query()->where('full_key', 'shop:viewer')->first();
    expect($perm)->not->toBeNull()
        ->and($perm->deprecated_at)->not->toBeNull()
        ->and($role)->not->toBeNull()
        ->and($role->deprecated_at)->not->toBeNull()
        // the survivor stays active
        ->and(Permission::query()->where('full_key', 'shop:orders.refund')->first()->deprecated_at)->toBeNull();
});
