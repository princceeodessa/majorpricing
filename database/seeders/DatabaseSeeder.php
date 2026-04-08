<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\CatalogImportService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::query()->updateOrCreate(
            ['login' => 'manager'],
            [
                'name' => 'Каталог Менеджер',
                'company' => 'MAJOR',
                'email' => 'manager@major.local',
                'email_verified_at' => now(),
                'password' => 'MajorDemo123!',
                'price_profile_id' => null,
                'is_active' => true,
                'is_manager' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['login' => 'partner'],
            [
                'name' => 'Партнер',
                'company' => 'Партнерский кабинет',
                'email' => 'partner@major.local',
                'email_verified_at' => now(),
                'password' => 'MajorDemo123!',
                'price_profile_id' => null,
                'is_active' => true,
                'is_manager' => false,
            ],
        );

        User::query()->updateOrCreate(
            ['login' => 'vip'],
            [
                'name' => 'VIP Клиент',
                'company' => 'Ключевой заказчик',
                'email' => 'vip@major.local',
                'email_verified_at' => now(),
                'password' => 'MajorDemo123!',
                'price_profile_id' => null,
                'is_active' => true,
                'is_manager' => false,
            ],
        );

        $catalogPath = storage_path('app/price-import.xlsx');

        if (is_file($catalogPath)) {
            app(CatalogImportService::class)->import($catalogPath);
        }
    }
}
