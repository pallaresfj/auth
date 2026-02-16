<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogger;
use Laravel\Passport\Events\AccessTokenCreated;

class LogAccessTokenCreated
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function handle(AccessTokenCreated $event): void
    {
        $user = null;

        if ($event->userId !== null) {
            $user = User::query()->find($event->userId);
        }

        $this->auditLogger->log('token_issued', 'success', $user, (string) $event->clientId, [
            'token_id' => $event->tokenId,
            'grant_type' => request()->input('grant_type'),
        ]);
    }
}
