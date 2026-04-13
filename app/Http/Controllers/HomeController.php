<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('catalog.index');
        }

        $featuredCategories = collect();
        $categoryCount = 0;
        $productCount = 0;

        if (Schema::hasTable('categories')) {
            $featuredCategories = Category::query()
                ->visibleInCatalog()
                ->roots()
                ->limit(8)
                ->get(['id', 'name', 'slug']);

            $categoryCount = Category::query()
                ->visibleInCatalog()
                ->count();
        }

        if (Schema::hasTable('products')) {
            $productCount = Product::query()
                ->visibleInCatalog()
                ->count();
        }

        return view('home.index', [
            'featuredCategories' => $featuredCategories,
            'categoryCount' => $categoryCount,
            'productCount' => $productCount,
        ]);
    }

    public function sitemap(): Response
    {
        $urls = collect([
            [
                'loc' => route('home'),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '1.0',
            ],
        ]);

        return response()
            ->view('home.sitemap', [
                'urls' => $urls,
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
