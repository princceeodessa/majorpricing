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
            $table->json('contact_people')->nullable()->after('contact_person');
            $table->json('messengers')->nullable()->after('telegram');
        });

        User::query()->select(['id', 'contact_person', 'telegram'])->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                $contactPeople = filled($user->contact_person) ? [trim((string) $user->contact_person)] : null;
                $messengers = filled($user->telegram) ? [trim((string) $user->telegram)] : null;

                $user->forceFill([
                    'contact_people' => $contactPeople,
                    'messengers' => $messengers,
                ])->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['contact_people', 'messengers']);
        });
    }
};
