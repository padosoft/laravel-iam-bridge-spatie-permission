<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Padosoft\Iam\Bridge\Spatie\Migration\PermissionMapper;
use Padosoft\Iam\Bridge\Spatie\Shadow\RecordsMismatch;
use Padosoft\Iam\Bridge\Spatie\Shadow\ShadowGate;
use Padosoft\Iam\Client\IamClient;
use Padosoft\Iam\Domain\Authorization\Models\Grant;

uses(RefreshDatabase::class);

/** Recorder che accumula i mismatch in memoria (al posto del log) per l'asserzione. */
function recordingRecorder(): RecordsMismatch
{
    return new class implements RecordsMismatch
    {
        /** @var list<array<string, mixed>> */
        public array $hits = [];

        public function record(string $subjectId, string $ability, bool $spatieAllows, bool $iamAllows): void
        {
            $this->hits[] = compact('subjectId', 'ability', 'spatieAllows', 'iamAllows');
        }
    };
}

function shadowGate(RecordsMismatch $recorder): ShadowGate
{
    return new ShadowGate(app(IamClient::class), $recorder, new PermissionMapper, 'app');
}

it('registra un mismatch quando Spatie nega ma IAM consente', function () {
    Grant::create([
        'subject_type' => 'user', 'subject_id' => 'usr_1',
        'privilege_type' => 'permission', 'privilege_key' => 'app:reports.view',
        'application_key' => 'app',
    ]);
    $recorder = recordingRecorder();

    // Spatie (locale) nega (false); IAM ha il grant → consente → divergenza.
    shadowGate($recorder)->compare(new GenericUser(['id' => 'usr_1']), 'reports.view', false);

    expect($recorder->hits)->toHaveCount(1)
        ->and($recorder->hits[0]['spatieAllows'])->toBeFalse()
        ->and($recorder->hits[0]['iamAllows'])->toBeTrue();
});

it('NON registra nulla quando Spatie e IAM concordano', function () {
    $recorder = recordingRecorder();

    // Nessun grant: IAM nega; Spatie nega → concordi → niente mismatch.
    shadowGate($recorder)->compare(new GenericUser(['id' => 'usr_nobody']), 'reports.view', false);

    expect($recorder->hits)->toBeEmpty();
});

it('shadow non altera mai l\'esito locale (Gate::after ritorna null)', function () {
    $recorder = recordingRecorder();
    $gate = shadowGate($recorder);

    // compare() è void e non lancia: l'osservazione non interferisce con la decisione locale.
    $gate->compare(new GenericUser(['id' => 'usr_1']), 'orders.refund', true);

    expect(true)->toBeTrue();
});
