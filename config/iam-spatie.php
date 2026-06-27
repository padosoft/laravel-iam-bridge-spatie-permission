<?php

declare(strict_types=1);

/*
 * Bridge di migrazione da spatie/laravel-permission a Laravel IAM (doc 07). Pensato per un cutover
 * graduale e reversibile: prima si OSSERVA (shadow: Spatie decide, IAM decide in parallelo, si
 * registrano i mismatch), poi si ENFORCE (IAM è l'autorità, Spatie resta cache read-only).
 */
return [
    // 'shadow' = Spatie decide davvero, IAM in parallelo (diffing, nessun blocco).
    // 'enforce' = IAM è l'autorità.
    'mode' => env('IAM_SPATIE_MODE', 'shadow'),

    // Mappa una permission Spatie ("orders.refund") al full_key IAM ("billing:orders.refund").
    // 'application' è il prefisso applicato alle permission prive di namespace.
    'application' => env('IAM_SPATIE_APP', 'app'),

    // Spatie come cache read-only dopo il cutover: blocca/audita le scritture locali.
    'cache' => [
        'write_protection' => true,
        'sync_on_webhook' => true,
        'sync_on_login' => true,
    ],

    // Canale di log per i mismatch shadow (allow/deny divergenti tra Spatie e IAM).
    'mismatch_log_channel' => env('IAM_SPATIE_MISMATCH_CHANNEL'),
];
