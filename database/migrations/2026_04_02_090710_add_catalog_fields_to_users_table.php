<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('login')->after('id')->unique();
            $table->string('company')->nullable()->after('name');
            $table->foreignId('price_profile_id')->nullable()->after('password')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('price_profile_id');
            $table->index(['price_profile_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['price_profile_id', 'is_active']);
            $table->dropConstrainedForeignId('price_profile_id');
            $table->dropColumn(['login', 'company', 'is_active']);
        });
    }
};
