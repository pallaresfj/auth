<?php

namespace App\Entities;

use App\Models\User;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use OpenIDConnect\Claims\Traits\WithClaims;
use OpenIDConnect\Entities\Traits\WithCustomPermittedFor;
use OpenIDConnect\Interfaces\IdentityEntityInterface;

class IdentityEntity implements IdentityEntityInterface
{
    use EntityTrait;
    use WithClaims;
    use WithCustomPermittedFor;

    private User $user;

    /**
     * @param  mixed  $identifier
     */
    public function setIdentifier($identifier): void
    {
        $this->identifier = (string) $identifier;
        $this->user = User::query()->findOrFail($identifier);
    }

    /**
     * @param  string[]  $scopes
     * @return array<string, mixed>
     */
    public function getClaims(array $scopes = []): array
    {
        $avatarUrl = trim((string) $this->user->google_avatar_url);
        $picture = filter_var($avatarUrl, FILTER_VALIDATE_URL) ? $avatarUrl : null;

        return [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'is_active' => (bool) $this->user->is_active,
            'email_verified' => true,
            'picture' => $picture,
            'google_avatar_url' => $picture,
        ];
    }
}
