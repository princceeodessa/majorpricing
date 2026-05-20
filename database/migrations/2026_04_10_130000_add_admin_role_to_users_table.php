<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->after('is_manager');
        });

        DB::table('users')
            ->where('login', 'vip')
            ->where('email', 'vip@potolkovych.local')
            ->delete();

        if (DB::table('users')->where('login', 'admin')->exists()) {
            DB::table('users')
                ->where('login', 'admin')
                ->update([
                    'is_active' => true,
                    'is_manager' => false,
                    'is_admin' => true,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('users')->insert([
                'name' => 'Администратор',
                'company' => 'МАЖОР',
                'login' => 'admin',
                'email' => 'admin@potolkovych.local',
                'email_verified_at' => now(),
                'password' => Hash::make(env('ADMIN_INITIAL_PASSWORD', 'PotolkovychAdmin123!')),
                'price_profile_id' => null,
                'manager_id' => null,
                'is_active' => true,
                'is_manager' => false,
                'is_admin' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('users')
            ->where('login', 'admin')
            ->where('email', 'admin@potolkovych.local')
            ->delete();

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_admin');
        });
    }
};
