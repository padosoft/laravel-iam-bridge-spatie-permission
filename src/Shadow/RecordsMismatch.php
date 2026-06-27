<?php

declare(strict_types=1);

namespace Padosoft\Iam\Bridge\Spatie\Shadow;

/**
 * Astrazione del sink dei mismatch shadow (doc 07 §12): di default va a log (MismatchRecorder), ma
 * un'app può sostituirlo per inviare le divergenze a una dashboard/coda di review prima del cutover.
 */
interface RecordsMismatch
{
    public function record(string $subjectId, string $ability, bool $spatieAllows, bool $iamAllows): void;
}
