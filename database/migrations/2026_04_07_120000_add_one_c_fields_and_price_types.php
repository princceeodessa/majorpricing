<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('one_c_id')->nullable()->after('slug')->unique();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('one_c_id')->nullable()->after('slug')->unique();
            $table->string('vendor_code')->nullable()->after('one_c_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('one_c_document_id')->nullable()->after('integration_reference')->index();
            $table->timestamp('one_c_exported_at')->nullable()->after('integration_synced_at');
            $table->timestamp('one_c_updated_at')->nullable()->after('one_c_exported_at');
        });

        Schema::create('one_c_price_types', function (Blueprint $table): void {
            $table->id();
            $table->string('one_c_id')->unique();
            $table->string('name');
            $table->unsignedTinyInteger('column_index')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('one_c_price_types');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'one_c_document_id',
                'one_c_exported_at',
                'one_c_updated_at',
            ]);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'one_c_id',
                'vendor_code',
            ]);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn('one_c_id');
        });
    }
};
