<?php

namespace App\Services;

use App\Models\AuditLogin;
use App\Models\User;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function log(
        string $event,
        string $status,
        ?User $user = null,
        ?string $clientId = null,
        array $meta = []
    ): void {
        AuditLogin::query()->create([
            'user_id' => $user?->id,
            'client_id' => $clientId,
            'ip' => (string) request()?->ip(),
            'user_agent' => (string) request()?->userAgent(),
            'event' => $event,
            'status' => $status,
            'meta' => $meta,
        ]);
    }
}
