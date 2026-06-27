<?php

declare(strict_types=1);

namespace Padosoft\Iam\Bridge\Spatie\Migration;

/**
 * Genera un manifest `laravel-iam.manifest.v2` (doc 07 §7) dall'inventory Spatie. Mapping:
 * Spatie permissions → manifest.permissions, Spatie roles → manifest.roles, role_has_permissions →
 * roles[].permissions. Le chiavi sono slugificate e DEDUPLICATE (due nomi che collidono sulla stessa
 * chiave = duplicato semantico: si tiene il primo). Il `risk` è un'euristica di partenza per la
 * review umana, non una verità: il manifest va sempre validato e approvato prima del sync.
 */
final class ManifestGenerator
{
    public function __construct(private readonly PermissionMapper $mapper) {}

    /**
     * @param  array<string, mixed>  $scan  output di SpatieScanner::scan()
     * @param  array<string, mixed>  $app  metadati app: key (richiesto), name, type, risk_level
     * @return array<string, mixed>
     */
    public function generate(array $scan, array $app): array
    {
        $appKey = is_string($app['key'] ?? null) && $app['key'] !== '' ? $app['key'] : 'legacy';
        $permissions = [];
        $seen = [];

        foreach (is_array($scan['permissions'] ?? null) ? $scan['permissions'] : [] as $perm) {
            $name = is_array($perm) && is_string($perm['name'] ?? null) ? $perm['name'] : (is_string($perm) ? $perm : '');
            if ($name === '') {
                continue;
            }
            $key = $this->mapper->toKey($name);
            if (isset($seen[$key])) {
                continue; // duplicato semantico → si tiene il primo (smell da rivedere)
            }
            $seen[$key] = true;
            $permissions[] = ['key' => $key, 'risk' => $this->mapper->inferRisk($key)];
        }

        $roles = [];
        foreach (is_array($scan['roles'] ?? null) ? $scan['roles'] : [] as $role) {
            if (!is_array($role) || !is_string($role['name'] ?? null) || $role['name'] === '') {
                continue;
            }
            $permKeys = [];
            foreach (is_array($role['permissions'] ?? null) ? $role['permissions'] : [] as $permName) {
                if (is_string($permName) && $permName !== '') {
                    $mapped = $this->mapper->toKey($permName);
                    if (isset($seen[$mapped]) && !in_array($mapped, $permKeys, true)) {
                        $permKeys[] = $mapped;
                    }
                }
            }
            $roles[] = ['key' => $this->mapper->toKey($role['name']), 'permissions' => $permKeys];
        }

        return [
            'schema' => 'laravel-iam.manifest.v2',
            'app' => [
                'key' => $appKey,
                'name' => is_string($app['name'] ?? null) && $app['name'] !== '' ? $app['name'] : $appKey,
                'type' => is_string($app['type'] ?? null) ? $app['type'] : 'laravel',
                'risk_level' => is_string($app['risk_level'] ?? null) ? $app['risk_level'] : 'low',
            ],
            'permissions' => $permissions,
            'roles' => $roles,
        ];
    }
}
