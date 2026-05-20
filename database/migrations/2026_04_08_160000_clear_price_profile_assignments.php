<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->update([
            'price_profile_id' => null,
        ]);

        DB::table('orders')->update([
            'price_profile_name' => null,
        ]);
    }

    public function down(): void
    {
        // Old per-profile assignments are intentionally not restored.
    }
};
