<?php

namespace Database\Seeders;

use App\Models\PriceProfile;
use App\Models\User;
use App\Services\CatalogImportService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $profiles = collect([
            [
                'name' => 'Базовый прайс',
                'slug' => 'base-price',
                'column_index' => 1,
                'price_label' => 'Цена 1',
                'description' => 'Основной прайс для менеджеров и базовых клиентов.',
                'is_default' => true,
            ],
            [
                'name' => 'Партнерский прайс',
                'slug' => 'partner-price',
                'column_index' => 2,
                'price_label' => 'Цена 2',
                'description' => 'Адаптированный прайс для постоянных партнеров.',
                'is_default' => false,
            ],
            [
                'name' => 'VIP прайс',
                'slug' => 'vip-price',
                'column_index' => 3,
                'price_label' => 'Цена 3',
                'description' => 'Расширенный прайс для ключевых клиентов.',
                'is_default' => false,
            ],
            [
                'name' => 'Спецусловия',
                'slug' => 'special-price',
                'column_index' => 4,
                'price_label' => 'Цена 4',
                'description' => 'Дополнительный профиль под индивидуальные условия.',
                'is_default' => false,
            ],
        ])->mapWithKeys(function (array $profile): array {
            $model = PriceProfile::query()->updateOrCreate(
                ['slug' => $profile['slug']],
                $profile,
            );

            return [$profile['slug'] => $model];
        });

        User::query()->updateOrCreate(
            ['login' => 'manager'],
            [
                'name' => 'Каталог Менеджер',
                'company' => 'MAJOR',
                'email' => 'manager@major.local',
                'email_verified_at' => now(),
                'password' => 'MajorDemo123!',
                'price_profile_id' => $profiles['base-price']->id,
                'is_active' => true,
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
                'price_profile_id' => $profiles['partner-price']->id,
                'is_active' => true,
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
                'price_profile_id' => $profiles['vip-price']->id,
                'is_active' => true,
            ],
        );

        $catalogPath = storage_path('app/price-import.xlsx');

        if (is_file($catalogPath)) {
            app(CatalogImportService::class)->import($catalogPath);
        }
    }
}
