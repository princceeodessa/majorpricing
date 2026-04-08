<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('manager_id')
                ->nullable()
                ->after('password')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::create('support_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
            $table->index(['manager_id', 'created_at']);
        });

        $managerIds = User::query()
            ->where('is_manager', true)
            ->orderBy('id')
            ->pluck('id');

        if ($managerIds->count() === 1) {
            User::query()
                ->where('is_manager', false)
                ->whereNull('manager_id')
                ->update(['manager_id' => $managerIds->first()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manager_id');
        });
    }
};
