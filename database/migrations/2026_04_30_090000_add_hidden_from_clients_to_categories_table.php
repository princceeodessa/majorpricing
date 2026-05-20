<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->boolean('is_hidden_from_clients')->default(false)->after('accent_color');
            $table->index('is_hidden_from_clients');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex(['is_hidden_from_clients']);
            $table->dropColumn('is_hidden_from_clients');
        });
    }
};
