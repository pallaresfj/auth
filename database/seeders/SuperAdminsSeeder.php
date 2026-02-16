<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminsSeeder extends Seeder
{
    public function run(): void
    {
        $emails = config('sso.superadmin_emails', []);

        foreach ($emails as $email) {
            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => str($email)->before('@')->replace(['.', '_', '-'], ' ')->title()->toString(),
                    'is_active' => true,
                ],
            );
        }
    }
}
