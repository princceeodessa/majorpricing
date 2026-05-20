<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FavoriteItem;
use App\Models\PriceProfile;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoritesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_product_to_favorites_and_see_it_on_page(): void
    {
        $profile = PriceProfile::query()->create([
            'name' => 'Базовый прайс',
            'slug' => 'base-price',
            'column_index' => 1,
            'price_label' => 'Цена 1',
            'is_default' => true,
        ]);

        $user = User::factory()->create([
            'login' => 'favorite-user',
            'email' => 'favorite-user@example.com',
            'password' => 'secret12345',
            'price_profile_id' => $profile->id,
        ]);

        $category = Category::query()->create([
            'name' => 'Профиля',
            'slug' => 'profilya',
            'sort_order' => 0,
            'accent_color' => '#f97316',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Профиль любимый',
            'name' => 'Профиль любимый',
            'slug' => 'profil-favorite',
            'source_sheet' => 'Major',
            'price_from' => 530,
            'sort_order' => 0,
        ]);

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'column_index' => 1,
            'label' => 'Цена 1',
            'display_value' => '530,00',
            'min_amount' => 530,
        ]);

        $response = $this->actingAs($user)->post(route('favorites.store', $product));

        $response->assertRedirect();
        $this->assertDatabaseHas('favorite_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $favoritesPage = $this->actingAs($user)->get(route('favorites.index'));

        $favoritesPage->assertOk();
        $favoritesPage->assertSeeText('Личный список товаров');
        $favoritesPage->assertSeeText('Профиль любимый');
        $favoritesPage->assertSee('data-favorited="1"', false);
    }

    public function test_user_can_remove_product_from_favorites(): void
    {
        $profile = PriceProfile::query()->create([
            'name' => 'Базовый прайс',
            'slug' => 'base-price',
            'column_index' => 1,
            'price_label' => 'Цена 1',
            'is_default' => true,
        ]);

        $user = User::factory()->create([
            'login' => 'favorite-user-2',
            'email' => 'favorite-user-2@example.com',
            'password' => 'secret12345',
            'price_profile_id' => $profile->id,
        ]);

        $category = Category::query()->create([
            'name' => 'Инструмент',
            'slug' => 'instrument',
            'sort_order' => 0,
            'accent_color' => '#2563eb',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Шпатель для избранного',
            'name' => 'Шпатель для избранного',
            'slug' => 'shpatel-favorite',
            'source_sheet' => 'Hanov',
            'price_from' => 1200,
            'sort_order' => 0,
        ]);

        FavoriteItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $response = $this->actingAs($user)->delete(route('favorites.destroy', $product));

        $response->assertRedirect();
        $this->assertDatabaseMissing('favorite_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $favoritesPage = $this->actingAs($user)->get(route('favorites.index'));

        $favoritesPage->assertOk();
        $favoritesPage->assertSeeText('Избранное пока пусто');
    }

    public function test_favorite_toggle_returns_json_for_async_requests(): void
    {
        $profile = PriceProfile::query()->create([
            'name' => 'Базовый прайс',
            'slug' => 'base-price',
            'column_index' => 1,
            'price_label' => 'Цена 1',
            'is_default' => true,
        ]);

        $user = User::factory()->create([
            'login' => 'favorite-json',
            'email' => 'favorite-json@example.com',
            'password' => 'secret12345',
            'price_profile_id' => $profile->id,
        ]);

        $category = Category::query()->create([
            'name' => 'KRAAB',
            'slug' => 'kraab',
            'sort_order' => 0,
            'accent_color' => '#f97316',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'AIRKRAAB 2.0',
            'name' => 'AIRKRAAB 2.0',
            'slug' => 'airkraab-2',
            'source_sheet' => 'KRAAB',
            'price_from' => 730,
            'sort_order' => 0,
        ]);

        $storeResponse = $this->actingAs($user)->postJson(route('favorites.store', $product));

        $storeResponse
            ->assertOk()
            ->assertJson([
                'favorited' => true,
                'productId' => $product->id,
                'favoritesCount' => 1,
            ]);

        $destroyResponse = $this->actingAs($user)->deleteJson(route('favorites.destroy', $product));

        $destroyResponse
            ->assertOk()
            ->assertJson([
                'favorited' => false,
                'productId' => $product->id,
                'favoritesCount' => 0,
            ]);
    }
}
