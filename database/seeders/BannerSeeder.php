<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        $placeholders = [
            [
                'title' => 'Заглушка: Акции и скидки',
                'placeholder_color' => 'linear-gradient(135deg, #d60000 0%, #b40d12 100%)',
                'placeholder_text' => 'Акции и скидки',
                'sort_order' => 10,
            ],
            [
                'title' => 'Заглушка: Новые поступления',
                'placeholder_color' => 'linear-gradient(135deg, #4a0509 0%, #b40d12 100%)',
                'placeholder_text' => 'Новые поступления',
                'sort_order' => 20,
            ],
            [
                'title' => 'Заглушка: Программа дилеров',
                'placeholder_color' => 'linear-gradient(135deg, #f97316 0%, #d60000 100%)',
                'placeholder_text' => 'Программа дилеров',
                'sort_order' => 30,
            ],
        ];

        foreach ($placeholders as $row) {
            Banner::query()->updateOrCreate(
                ['title' => $row['title']],
                array_merge($row, ['is_active' => true]),
            );
        }
    }
}
