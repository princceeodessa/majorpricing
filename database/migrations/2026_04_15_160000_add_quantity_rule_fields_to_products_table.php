<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'minimum_sale_quantity')) {
                $table->decimal('minimum_sale_quantity', 14, 3)->nullable()->after('stock_quantity');
            }

            if (! Schema::hasColumn('products', 'units_in_package')) {
                $table->decimal('units_in_package', 14, 3)->nullable()->after('minimum_sale_quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $dropColumns = [];

            if (Schema::hasColumn('products', 'units_in_package')) {
                $dropColumns[] = 'units_in_package';
            }

            if (Schema::hasColumn('products', 'minimum_sale_quantity')) {
                $dropColumns[] = 'minimum_sale_quantity';
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
