<?php

declare(strict_types=1);

namespace Padosoft\Iam\Bridge\Spatie\Migration;

/**
 * Mappa i nomi Spatie ("orders.refund", "Manage Users") sulle chiavi del manifest IAM, che devono
 * rispettare lo slug `^[a-z][a-z0-9_.-]*$` (doc 01 §10 / ManifestValidator). La trasformazione è
 * deterministica e idempotente: due nomi che slugificano alla stessa chiave sono un duplicato
 * semantico da rivedere a mano (doc 07 §5).
 */
final class PermissionMapper
{
    /** Azioni a rischio elevato: euristica di partenza per il `risk` del manifest (review umana). */
    private const HIGH_RISK = ['refund', 'delete', 'destroy', 'drop', 'truncate', 'grant', 'revoke', 'impersonate', 'export', 'approve', 'disable', 'suspend', 'wipe'];

    public function toKey(string $name): string
    {
        $key = strtolower(trim($name));
        $key = preg_replace('/[^a-z0-9_.-]+/', '_', $key) ?? $key;
        $key = preg_replace('/_{2,}/', '_', $key) ?? $key;
        $key = trim($key, '_.-');

        if ($key === '') {
            return 'perm';
        }
        // Lo slug DEVE iniziare con [a-z]: un nome che inizia per cifra/simbolo va prefissato.
        if (preg_match('/^[a-z]/', $key) !== 1) {
            $key = 'p_'.$key;
        }

        return $key;
    }

    /** full_key IAM = `<app>:<key>` (il server lo deriva, ma serve per i grant/shadow). */
    public function toFullKey(string $application, string $name): string
    {
        return str_contains($name, ':') ? $name : $application.':'.$this->toKey($name);
    }

    /** Euristica di rischio sull'azione (ultimo segmento dopo `.`); 'low' se nessun match. */
    public function inferRisk(string $key): string
    {
        $segments = explode('.', $key);
        $action = end($segments);

        return in_array($action, self::HIGH_RISK, true) ? 'high' : 'low';
    }
}
