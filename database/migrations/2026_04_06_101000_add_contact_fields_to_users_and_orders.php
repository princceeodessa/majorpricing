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
            $table->string('contact_person')->nullable()->after('company');
            $table->string('phone', 64)->nullable()->after('contact_person');
            $table->string('telegram')->nullable()->after('phone');
            $table->text('delivery_address')->nullable()->after('telegram');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('customer_name')->nullable()->after('price_profile_name');
            $table->string('customer_company')->nullable()->after('customer_name');
            $table->string('customer_email')->nullable()->after('customer_company');
            $table->string('customer_phone', 64)->nullable()->after('customer_email');
            $table->string('customer_contact_person')->nullable()->after('customer_phone');
            $table->string('customer_telegram')->nullable()->after('customer_contact_person');
            $table->text('customer_delivery_address')->nullable()->after('customer_telegram');
            $table->text('manager_comment')->nullable()->after('comment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'customer_name',
                'customer_company',
                'customer_email',
                'customer_phone',
                'customer_contact_person',
                'customer_telegram',
                'customer_delivery_address',
                'manager_comment',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'contact_person',
                'phone',
                'telegram',
                'delivery_address',
            ]);
        });
    }
};
