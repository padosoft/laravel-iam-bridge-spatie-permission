<?php

declare(strict_types=1);

namespace Padosoft\Iam\Bridge\Spatie\Shadow;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Padosoft\Iam\Bridge\Spatie\Migration\PermissionMapper;
use Padosoft\Iam\Client\IamClient;

/**
 * Gate adapter in modalità SHADOW (doc 07 §12): Spatie decide davvero, IAM decide in parallelo e si
 * registrano i mismatch — nessun utente viene mai bloccato. Usa `Gate::after`, che vede l'esito reale
 * della valutazione locale e restituisce `null` per NON alterarlo. È il passo di osservazione che
 * precede l'`enforce`.
 */
final class ShadowGate
{
    public function __construct(
        private readonly IamClient $client,
        private readonly RecordsMismatch $recorder,
        private readonly PermissionMapper $mapper,
        private readonly string $application,
    ) {}

    public function register(Gate $gate): void
    {
        $gate->after(function (Authenticatable $user, string $ability, ?bool $result, array $arguments = []): ?bool {
            $this->compare($user, $ability, $result, $arguments);

            return null; // shadow: non si cambia MAI l'esito locale
        });
    }

    /**
     * @param  array<array-key, mixed>  $arguments
     */
    public function compare(Authenticatable $user, string $ability, ?bool $localResult, array $arguments = []): void
    {
        $iamAbility = str_contains($ability, ':')
            ? $ability
            : $this->application.':'.$this->mapper->toKey($ability);

        $context = ['application' => $this->application];
        $first = $arguments[0] ?? null;
        if (is_string($first) && $first !== '') {
            $context['resource'] = $first;
        }

        $iamAllows = $this->client->can($user, $iamAbility, $context);
        $spatieAllows = $this->spatieAllows($user, $ability, $localResult);

        if ($iamAllows !== $spatieAllows) {
            $this->recorder->record($this->client->resolveSubjectId($user), $ability, $spatieAllows, $iamAllows);
        }
    }

    /**
     * Decisione di Spatie. Si interroga Spatie DIRETTAMENTE (`hasPermissionTo`) quando disponibile,
     * invece di fidarsi del `$result` di `Gate::after`: quel risultato può essere stato cortocircuitato
     * da un altro `Gate::before` (es. l'enforce del client), facendo confrontare IAM con IAM → zero
     * mismatch falsi → cutover su dati invalidi. Senza il trait Spatie si ricade sul risultato del Gate.
     */
    private function spatieAllows(Authenticatable $user, string $ability, ?bool $gateResult): bool
    {
        $probe = [$user, 'hasPermissionTo'];
        if (is_callable($probe)) {
            try {
                return (bool) $probe($ability);
            } catch (\Throwable) {
                return false; // permesso non noto a Spatie → deny
            }
        }

        return $gateResult === true;
    }
}
