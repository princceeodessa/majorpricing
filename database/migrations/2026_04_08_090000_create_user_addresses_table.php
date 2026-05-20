<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('address');
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('users')
            ->select(['id', 'delivery_address'])
            ->whereNotNull('delivery_address')
            ->where('delivery_address', '<>', '')
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($now): void {
                $payload = [];

                foreach ($users as $user) {
                    $payload[] = [
                        'user_id' => $user->id,
                        'title' => 'Основной адрес',
                        'address' => trim((string) $user->delivery_address),
                        'is_default' => true,
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($payload !== []) {
                    DB::table('user_addresses')->insert($payload);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
