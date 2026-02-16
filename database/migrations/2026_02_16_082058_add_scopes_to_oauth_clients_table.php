<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('oauth_clients', 'scopes')) {
            return;
        }

        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->json('scopes')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('oauth_clients', 'scopes')) {
            return;
        }

        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropColumn('scopes');
        });
    }
};
