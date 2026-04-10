<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where(function ($query): void {
                $query
                    ->where('login', 'vip')
                    ->orWhere('email', 'vip@major.local')
                    ->orWhere('email', 'vip@potolkovych.local')
                    ->orWhere('name', 'VIP Клиент');
            })
            ->delete();
    }

    public function down(): void
    {
        //
    }
};
