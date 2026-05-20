<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('payment_status')->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable()->index();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('integration_reference')->nullable()->index();
            $table->timestamp('integration_synced_at')->nullable();
            $table->json('payment_payload')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['payment_reference']);
            $table->dropIndex(['integration_reference']);
            $table->dropColumn([
                'payment_status',
                'payment_method',
                'payment_reference',
                'paid_amount',
                'paid_at',
                'integration_reference',
                'integration_synced_at',
                'payment_payload',
            ]);
        });
    }
};
