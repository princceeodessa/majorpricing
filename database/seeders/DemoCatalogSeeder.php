<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\PriceProfile;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Демо-каталог для локальной разработки и дизайнерских правок.
 * Заполняет ~12 товаров в 4 категориях с двумя ценами (нал + безнал)
 * и привязывает базовый ценовой профиль к demo-партнёру.
 *
 * Идемпотентен: повторный запуск ничего не ломает.
 * Никак не трогает данные с 1С (one_c_id остаётся пустым).
 */
class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ценовой профиль по умолчанию (column_index=1 = нал, compare=2 = безнал)
        $profile = PriceProfile::query()->updateOrCreate(
            ['slug' => 'demo-dealer'],
            [
                'name' => 'Демо-дилер',
                'column_index' => 1,
                'compare_column_index' => 2,
                'price_label' => 'Цена для дилера',
                'description' => 'Профиль по умолчанию для локального демо.',
                'is_default' => true,
            ],
        );

        // 2. Назначить partner-аккаунту, если уже создан DatabaseSeeder'ом
        User::query()->where('login', 'partner')->update(['price_profile_id' => $profile->id]);

        // 3. Категории
        $categories = [
            ['name' => 'Светильники',         'slug' => 'svetilniki',         'accent' => '#d60000', 'sort' => 10],
            ['name' => 'Натяжные потолки',    'slug' => 'natyazhnye-potolki', 'accent' => '#b40d12', 'sort' => 20],
            ['name' => 'Профили и крепёж',    'slug' => 'profili-i-krepezh',  'accent' => '#4a0509', 'sort' => 30],
            ['name' => 'Аксессуары',          'slug' => 'aksessuary',         'accent' => '#f97316', 'sort' => 40],
        ];

        $catModels = [];
        foreach ($categories as $cat) {
            $catModels[$cat['slug']] = Category::query()->updateOrCreate(
                ['slug' => $cat['slug']],
                [
                    'name' => $cat['name'],
                    'parent_id' => null,
                    'description' => null,
                    'sort_order' => $cat['sort'],
                    'accent_color' => $cat['accent'],
                ],
            );
        }

        // 4. Товары — 3 позиции в каждую категорию
        $products = [
            // Светильники
            ['cat' => 'svetilniki', 'title' => 'LumFer Clamp LUX 70 (2м) чёрный',     'color' => 'чёрный',  'unit' => 'м',  'stock' => 64,  'min' => 2,   'pack' => 2,  'p1' => 1280, 'p2' => 1480, 'brand' => 'LumFer'],
            ['cat' => 'svetilniki', 'title' => 'LumFer Clamp LUX 70 (2м) серебро',    'color' => 'серебро','unit' => 'м',  'stock' => 28,  'min' => 2,   'pack' => 2,  'p1' => 1280, 'p2' => 1480, 'brand' => 'LumFer'],
            ['cat' => 'svetilniki', 'title' => 'PARSEK Профиль ПК-12 3-х рядный',     'color' => 'белый',   'unit' => 'м',  'stock' => 10,  'min' => 1,   'pack' => 1,  'p1' => 540,  'p2' => 620,  'brand' => 'PARSEK'],

            // Натяжные потолки
            ['cat' => 'natyazhnye-potolki', 'title' => 'Полотно глянец 3.2м белый MSD Premium',    'color' => 'белый',  'unit' => 'м²', 'stock' => 220, 'min' => 5, 'pack' => 1, 'p1' => 285, 'p2' => 320, 'brand' => 'MSD'],
            ['cat' => 'natyazhnye-potolki', 'title' => 'Полотно мат 3.2м белый PONGS',             'color' => 'белый',  'unit' => 'м²', 'stock' => 350, 'min' => 5, 'pack' => 1, 'p1' => 310, 'p2' => 360, 'brand' => 'PONGS'],
            ['cat' => 'natyazhnye-potolki', 'title' => 'Полотно сатин 3.2м белый CTN',             'color' => 'белый',  'unit' => 'м²', 'stock' => 180, 'min' => 5, 'pack' => 1, 'p1' => 295, 'p2' => 340, 'brand' => 'CTN'],

            // Профили и крепёж
            ['cat' => 'profili-i-krepezh', 'title' => 'Профиль алюминиевый ПК-1 стеновой 2.5м',    'color' => 'серебро', 'unit' => 'шт', 'stock' => 480, 'min' => 1, 'pack' => 1, 'p1' => 195, 'p2' => 225, 'brand' => 'МАЖОР'],
            ['cat' => 'profili-i-krepezh', 'title' => 'Профиль ПЛ-100 потолочный 2.5м',            'color' => 'белый',   'unit' => 'шт', 'stock' => 320, 'min' => 1, 'pack' => 1, 'p1' => 220, 'p2' => 255, 'brand' => 'МАЖОР'],
            ['cat' => 'profili-i-krepezh', 'title' => 'Заглушка торцевая универсальная белая',    'color' => 'белый',   'unit' => 'шт', 'stock' => 1200,'min' => 4, 'pack' => 4, 'p1' => 35,  'p2' => 42,  'brand' => 'МАЖОР'],

            // Аксессуары
            ['cat' => 'aksessuary', 'title' => 'Гарпун ПВХ для натяжного потолка 50м',  'color' => null,      'unit' => 'м',  'stock' => 5000, 'min' => 10,'pack' => 1, 'p1' => 18, 'p2' => 22, 'brand' => 'МАЖОР'],
            ['cat' => 'aksessuary', 'title' => 'Лента маскировочная декоративная 10м',  'color' => 'белый',   'unit' => 'шт', 'stock' => 220,  'min' => 1, 'pack' => 1, 'p1' => 145,'p2' => 170,'brand' => 'МАЖОР'],
            ['cat' => 'aksessuary', 'title' => 'Сертификат А5 300гр 4+4',                'color' => null,      'unit' => 'шт', 'stock' => 900,  'min' => 1, 'pack' => 1, 'p1' => 8,  'p2' => 10, 'brand' => 'МАЖОР'],
        ];

        foreach ($products as $p) {
            $cat = $catModels[$p['cat']];
            $slug = Str::slug(Str::limit($p['title'], 80, ''), '-');
            // Slug must be unique — keep deterministic suffix from brand
            $slug = $slug.'-'.Str::slug($p['brand']);

            $product = Product::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $cat->id,
                    'title' => $p['title'],
                    'name' => $p['title'],
                    'brand_name' => $p['brand'],
                    'color_name' => $p['color'],
                    'measurement_label' => $p['unit'],
                    'measurement_value' => $p['unit'],
                    'stock_quantity' => $p['stock'],
                    'minimum_sale_quantity' => $p['min'],
                    'units_in_package' => $p['pack'],
                    'description' => 'Демо-товар для локального просмотра дизайна. Подменяется реальной 1С-выгрузкой на проде.',
                    'price_from' => $p['p1'],
                    'price_preview' => number_format($p['p1'], 0, '.', ' ').' ₽ за '.$p['unit'],
                ],
            );

            // Две цены: нал (column_index=1) + безнал (column_index=2)
            foreach ([['idx' => 1, 'label' => 'Дилер нал',    'amount' => $p['p1']],
                      ['idx' => 2, 'label' => 'Дилер безнал', 'amount' => $p['p2']]] as $price) {
                ProductPrice::query()->updateOrCreate(
                    ['product_id' => $product->id, 'column_index' => $price['idx']],
                    [
                        'label' => $price['label'],
                        'display_value' => number_format($price['amount'], 2, ',', ' '),
                        'min_amount' => $price['amount'],
                    ],
                );
            }
        }

        $this->command?->info(sprintf(
            'DemoCatalogSeeder: %d категорий, %d товаров готово.',
            count($categories),
            count($products),
        ));
    }
}
