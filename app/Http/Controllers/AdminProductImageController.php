<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AdminProductImageController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim($request->string('q')->toString());

        $products = Product::query()
            ->with(['category', 'productImages'])
            ->when(
                $query !== '',
                fn ($builder) => $builder->where(function ($subQuery) use ($query): void {
                    $needle = '%'.preg_replace('/\s+/u', '%', $query).'%';

                    $subQuery
                        ->where('title', 'like', $needle)
                        ->orWhere('name', 'like', $needle)
                        ->orWhere('vendor_code', 'like', $needle)
                        ->orWhere('one_c_code', 'like', $needle)
                        ->orWhere('one_c_id', 'like', $needle);
                }),
            )
            ->orderBy('title')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.product-images.index', [
            'products' => $products,
            'query' => $query,
        ]);
    }

    public function edit(Product $product): View
    {
        $product->load(['category', 'productImages']);

        return view('admin.product-images.edit', [
            'product' => $product,
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:20'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp,avif,svg', 'max:10240'],
        ]);

        $product->load('productImages');
        $nextSortOrder = ((int) $product->productImages->max('sort_order')) + 1;
        $hasCover = $product->productImages->contains(fn (ProductImage $image): bool => $image->is_cover);

        foreach ($validated['images'] as $uploadedImage) {
            $originalName = pathinfo((string) $uploadedImage->getClientOriginalName(), PATHINFO_FILENAME);
            $baseName = Str::slug($originalName);
            $baseName = $baseName !== '' ? $baseName : 'image';

            $extension = mb_strtolower((string) $uploadedImage->getClientOriginalExtension());
            $extension = $extension !== '' ? $extension : mb_strtolower((string) $uploadedImage->extension());
            $extension = $extension !== '' ? $extension : 'jpg';

            $fileName = sprintf(
                '%d-%s-%s.%s',
                $product->id,
                $baseName,
                Str::lower(Str::random(10)),
                $extension,
            );

            $uploadedImage->storeAs('catalog-media/uploads', $fileName, 'public');
            $path = 'storage/catalog-media/uploads/'.$fileName;

            $isCover = ! $hasCover;

            $product->productImages()->create([
                'path' => $path,
                'sort_order' => $nextSortOrder++,
                'is_cover' => $isCover,
            ]);

            if ($isCover) {
                $hasCover = true;
                $product->forceFill(['image_path' => $path])->save();
            }
        }

        return redirect()
            ->route('admin.products.images.edit', $product)
            ->with('status', 'Фотографии добавлены.');
    }

    public function cover(Product $product, ProductImage $productImage): RedirectResponse
    {
        abort_unless($productImage->product_id === $product->id, 404);

        DB::transaction(function () use ($product, $productImage): void {
            $product->productImages()->update(['is_cover' => false]);

            $productImage->forceFill(['is_cover' => true])->save();

            $product->forceFill(['image_path' => $productImage->path])->save();
        });

        return redirect()
            ->route('admin.products.images.edit', $product)
            ->with('status', 'Обложка товара обновлена.');
    }

    public function destroy(Product $product, ProductImage $productImage): RedirectResponse
    {
        abort_unless($productImage->product_id === $product->id, 404);

        $deletedPath = $productImage->path;
        $wasCover = $productImage->is_cover;

        $productImage->delete();

        $publicFile = public_path($deletedPath);
        if (File::exists($publicFile)) {
            File::delete($publicFile);
        }

        $nextCover = $product->productImages()->first();

        if ($nextCover) {
            if ($wasCover && ! $nextCover->is_cover) {
                $nextCover->forceFill(['is_cover' => true])->save();
            }

            $product->forceFill(['image_path' => $nextCover->path])->save();
        } elseif ($product->image_path === $deletedPath) {
            $product->forceFill(['image_path' => null])->save();
        }

        return redirect()
            ->route('admin.products.images.edit', $product)
            ->with('status', 'Фотография удалена.');
    }
}
