<?php

namespace App\Http\Resources\Mobile;

use App\Models\Product;
use App\Support\MobileApi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $publicPrice = $this->publicPrice();
        $comparePrice = $this->comparePrice();
        $imagePaths = $this->galleryImagePaths();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->publicTitle(),
            'full_name' => $this->fullTitle(),
            'article' => $this->vendor_code ?: $this->one_c_code,
            'brand_name' => $this->brand_name,
            'color_name' => $this->color_name,
            'description' => $this->description,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'parent_id' => $this->category->parent_id,
                'slug' => $this->category->slug,
                'name' => $this->category->name,
            ] : null,
            'image_url' => MobileApi::assetUrl($this->coverImagePath()),
            'images' => collect($imagePaths)->values()->map(fn (string $path, int $index): array => [
                'url' => MobileApi::assetUrl($path),
                'is_cover' => $index === 0,
            ])->all(),
            'price' => MobileApi::money($publicPrice?->min_amount),
            'compare_price' => MobileApi::money($comparePrice?->min_amount),
            'price_label' => $publicPrice?->label,
            'currency' => 'RUB',
            'stock_quantity' => MobileApi::quantity($this->stock_quantity),
            'stock_summary' => $this->stockSummary(),
            'is_in_stock' => $this->isInStock(),
            'availability_label' => $this->availabilityLabel(),
            'unit' => $this->publicUnitLabel(),
            'quantity_step' => $this->cartQuantityStep(),
            'min_quantity' => $this->cartQuantityMinimum(),
            'max_quantity' => $this->cartQuantityMax(),
            'minimum_sale_quantity' => MobileApi::quantity($this->minimum_sale_quantity),
            'units_in_package' => MobileApi::quantity($this->units_in_package),
            'is_favorite' => (bool) ($this->getAttribute('mobile_is_favorite') ?? false),
            'cart_quantity' => (int) ($this->getAttribute('mobile_cart_quantity') ?? 0),
        ];
    }
}
