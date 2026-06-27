<?php

declare(strict_types=1);

namespace Padosoft\Iam\Bridge\Spatie\Migration;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

/**
 * Inventory automatico di spatie/laravel-permission (doc 07 §5). Legge le tabelle Spatie via DB
 * (nomi da `permission.table_names`) e produce una mappa strutturata: ruoli + loro permessi,
 * permessi, assegnazioni dirette utente, guard. È SOLO lettura — non tocca nulla — e i suoi smell
 * (ruoli vuoti, permessi orfani, permessi diretti, guard multipli) alimentano il report di migrazione.
 */
final class SpatieScanner
{
    /** @param array<string, string> $tables override dei nomi tabella (default = standard Spatie) */
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly array $tables = [],
    ) {}

    /** @return array<string, mixed> */
    public function scan(): array
    {
        $t = $this->resolveTables();

        $permissions = $this->db->table($t['permissions'])->get(['id', 'name', 'guard_name']);
        $roles = $this->db->table($t['roles'])->get(['id', 'name', 'guard_name']);
        $rolePerms = $this->db->table($t['role_has_permissions'])->get(['role_id', 'permission_id']);
        $directPerms = $this->db->table($t['model_has_permissions'])->get();
        $modelRoles = $this->db->table($t['model_has_roles'])->get();

        $permById = [];
        $permNames = [];
        foreach ($permissions as $p) {
            $name = is_string($p->name ?? null) ? $p->name : '';
            $guard = is_string($p->guard_name ?? null) ? $p->guard_name : '';
            $permById[$this->key($p->id ?? null)] = $name;
            $permNames[] = ['name' => $name, 'guard' => $guard];
        }

        $permsByRole = [];
        foreach ($rolePerms as $rp) {
            $permsByRole[$this->key($rp->role_id ?? null)][] = $permById[$this->key($rp->permission_id ?? null)] ?? '';
        }

        $rolesOut = [];
        $guards = [];
        foreach ($roles as $r) {
            $guard = is_string($r->guard_name ?? null) ? $r->guard_name : '';
            $guards[$guard] = true;
            $perms = array_values(array_filter($permsByRole[$this->key($r->id ?? null)] ?? [], static fn (string $n): bool => $n !== ''));
            $rolesOut[] = [
                'name' => is_string($r->name ?? null) ? $r->name : '',
                'guard' => $guard,
                'permissions' => $perms,
            ];
        }
        foreach ($permNames as $p) {
            if ($p['guard'] !== '') {
                $guards[$p['guard']] = true;
            }
        }

        return [
            'permissions' => $permNames,
            'roles' => $rolesOut,
            'direct_user_permissions' => $this->mapDirect($directPerms, $permById),
            'model_has_roles_count' => $modelRoles->count(),
            'guards' => array_keys($guards),
        ];
    }

    /**
     * @param  Collection<int, \stdClass>  $rows
     * @param  array<string, string>  $permById
     * @return list<array<string, string>>
     */
    private function mapDirect($rows, array $permById): array
    {
        $out = [];
        foreach ($rows as $row) {
            $permId = $row->permission_id ?? null;
            $modelType = $row->model_type ?? null;
            $modelId = $row->model_id ?? null;
            $out[] = [
                'permission' => $permById[$this->key($permId)] ?? '',
                'model_type' => is_string($modelType) ? $modelType : '',
                'model_id' => $this->key($modelId),
            ];
        }

        return $out;
    }

    /** Normalizza un id/scalare grezzo del DB (mixed) in chiave stringa stabile. */
    private function key(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /** @return array<string, string> */
    private function resolveTables(): array
    {
        $defaults = [
            'permissions' => 'permissions',
            'roles' => 'roles',
            'role_has_permissions' => 'role_has_permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
        ];

        return array_merge($defaults, $this->tables);
    }
}
