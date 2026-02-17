<?php

namespace App\Models;

use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'google_id',
        'google_avatar_url',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->isSuperAdmin();
    }

    public function isSuperAdmin(): bool
    {
        $superAdminEmails = config('sso.superadmin_emails', []);

        return in_array(mb_strtolower((string) $this->email), $superAdminEmails, true);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        $avatarUrl = trim((string) $this->google_avatar_url);

        if ($avatarUrl === '') {
            return null;
        }

        if (! filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $avatarUrl;
    }

    public function auditLogins(): HasMany
    {
        return $this->hasMany(AuditLogin::class);
    }

    public function appAccesses(): HasMany
    {
        return $this->hasMany(UserAppAccess::class);
    }
}
