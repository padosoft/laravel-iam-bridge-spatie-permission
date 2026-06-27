<?php

declare(strict_types=1);

namespace Padosoft\Iam\Bridge\Spatie\Shadow;

use Psr\Log\LoggerInterface;

/**
 * Registra le divergenze di decisione tra Spatie (l'autorità in shadow mode) e IAM (in parallelo).
 * Ogni mismatch è il materiale che rende il cutover sicuro: prima di passare a `enforce` ci si
 * aspetta zero (o solo divergenze attese e spiegate). Il record va a log strutturato, non blocca mai.
 */
final class MismatchRecorder implements RecordsMismatch
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function record(string $subjectId, string $ability, bool $spatieAllows, bool $iamAllows): void
    {
        $this->logger->warning('iam.shadow.mismatch', [
            'subject_id' => $subjectId,
            'ability' => $ability,
            'spatie_allows' => $spatieAllows,
            'iam_allows' => $iamAllows,
            'direction' => $spatieAllows ? 'spatie_allow_iam_deny' : 'spatie_deny_iam_allow',
        ]);
    }
}
