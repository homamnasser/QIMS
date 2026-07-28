<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        // Tokens from the former mixed web/mobile login have no safe channel,
        // device pair, refresh policy, or expiry. They must not survive the
        // authentication boundary cutover.
        DB::table('personal_access_tokens')
            ->where('name', 'not like', 'mobile|%')
            ->delete();
    }

    public function down(): void
    {
        // Revoked plaintext credentials cannot and must not be reconstructed.
    }
};
