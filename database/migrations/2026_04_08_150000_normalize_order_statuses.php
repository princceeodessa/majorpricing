<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->where('status', 'processing')
            ->update(['status' => 'accepted']);

        DB::table('orders')
            ->where('status', 'payment_failed')
            ->update(['status' => 'new']);
    }

    public function down(): void
    {
        DB::table('orders')
            ->where('status', 'accepted')
            ->update(['status' => 'processing']);
    }
};
