<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_hide_category_branch_from_clients(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $root = Category::query()->create([
            'name' => 'Hidden root',
            'slug' => 'hidden-root',
            'sort_order' => 0,
            'accent_color' => '#0f172a',
        ]);

        $child = Category::query()->create([
            'parent_id' => $root->id,
            'name' => 'Hidden child',
            'slug' => 'hidden-child',
            'sort_order' => 0,
            'accent_color' => '#0f172a',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.catalog.visibility.index'))
            ->assertOk()
            ->assertSeeText('Видимость каталога')
            ->assertSeeText('Hidden root')
            ->assertSeeText('Hidden child');

        $response = $this->actingAs($admin)->patch(route('admin.catalog.visibility.update'), [
            'hidden_categories' => [$root->id],
        ]);

        $response
            ->assertRedirect(route('admin.catalog.visibility.index'))
            ->assertSessionHas('status');

        $this->assertTrue($root->fresh()->is_hidden_from_clients);
        $this->assertFalse($child->fresh()->is_hidden_from_clients);
        $this->assertContains($root->id, Category::hiddenFromCatalogIds());
        $this->assertContains($child->id, Category::hiddenFromCatalogIds());
    }

    public function test_hidden_category_products_are_not_visible_to_clients(): void
    {
        $user = User::factory()->create();

        $visibleCategory = Category::query()->create([
            'name' => 'Visible category',
            'slug' => 'visible-category',
            'sort_order' => 0,
            'accent_color' => '#16a34a',
        ]);

        $hiddenRoot = Category::query()->create([
            'name' => 'Hidden root',
            'slug' => 'hidden-root',
            'sort_order' => 1,
            'accent_color' => '#dc2626',
            'is_hidden_from_clients' => true,
        ]);

        $hiddenChild = Category::query()->create([
            'parent_id' => $hiddenRoot->id,
            'name' => 'Hidden child',
            'slug' => 'hidden-child',
            'sort_order' => 0,
            'accent_color' => '#dc2626',
        ]);

        Product::query()->create([
            'category_id' => $visibleCategory->id,
            'title' => 'Visible product',
            'name' => 'Visible product',
            'slug' => 'visible-product',
            'price_from' => 100,
            'sort_order' => 0,
        ]);

        $hiddenProduct = Product::query()->create([
            'category_id' => $hiddenChild->id,
            'title' => 'Hidden product',
            'name' => 'Hidden product',
            'slug' => 'hidden-product',
            'price_from' => 100,
            'sort_order' => 0,
        ]);

        $catalogResponse = $this->actingAs($user)->get(route('catalog.index'));

        $catalogResponse->assertOk();
        $catalogResponse->assertSeeText('Visible product');
        $catalogResponse->assertDontSeeText('Hidden product');
        $catalogResponse->assertDontSeeText('Hidden root');

        $this->actingAs($user)
            ->get(route('categories.show', $hiddenRoot))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('categories.show', $hiddenChild))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('products.show', $hiddenProduct))
            ->assertNotFound();
    }
}
