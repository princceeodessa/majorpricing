<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProductPriceSyncService
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{updated:int,skipped:int,errors:array<int,array<string,mixed>>}
     */
    public function sync(array $items, bool $resetMissing = false): array
    {
        $result = [
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($items as $index => $item) {
            $product = $this->resolveProduct($item);

            if (! $product) {
                $result['errors'][] = [
                    'index' => $index,
                    'message' => 'Product not found.',
                    'identifiers' => Arr::only($item, ['id', 'external_id', 'slug', 'source_sheet', 'source_row']),
                ];
                $result['skipped']++;

                continue;
            }

            DB::transaction(function () use ($product, $item, $resetMissing): void {
                $prices = collect($item['prices'] ?? [])
                    ->filter(fn ($price) => is_array($price) && isset($price['column_index']))
                    ->values();

                if ($resetMissing && $prices->isNotEmpty()) {
                    ProductPrice::query()
                        ->where('product_id', $product->id)
                        ->whereNotIn('column_index', $prices->pluck('column_index')->all())
                        ->delete();
                }

                $resolvedMinimums = [];

                foreach ($prices as $priceData) {
                    $amount = array_key_exists('min_amount', $priceData) && $priceData['min_amount'] !== null
                        ? (float) $priceData['min_amount']
                        : null;

                    ProductPrice::query()->updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'column_index' => (int) $priceData['column_index'],
                        ],
                        [
                            'label' => $priceData['label'] ?? ('Цена '.(int) $priceData['column_index']),
                            'display_value' => $priceData['display_value'] ?? $this->formatAmount($amount),
                            'min_amount' => $amount,
                        ],
                    );

                    if ($amount !== null) {
                        $resolvedMinimums[(int) $priceData['column_index']] = $amount;
                    }
                }

                ksort($resolvedMinimums);

                $product->forceFill([
                    'price_from' => array_key_exists('price_from', $item)
                        ? ($item['price_from'] !== null ? (float) $item['price_from'] : null)
                        : ($resolvedMinimums !== [] ? reset($resolvedMinimums) : $product->price_from),
                ])->save();
            });

            $result['updated']++;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveProduct(array $item): ?Product
    {
        if (filled($item['id'] ?? null)) {
            return Product::query()->find((int) $item['id']);
        }

        if (filled($item['external_id'] ?? null) && preg_match('/product-(\d+)/', (string) $item['external_id'], $matches)) {
            return Product::query()->find((int) $matches[1]);
        }

        if (filled($item['slug'] ?? null)) {
            return Product::query()->where('slug', $item['slug'])->first();
        }

        if (filled($item['source_sheet'] ?? null) && filled($item['source_row'] ?? null)) {
            return Product::query()
                ->where('source_sheet', $item['source_sheet'])
                ->where('source_row', (int) $item['source_row'])
                ->first();
        }

        return null;
    }

    private function formatAmount(?float $amount): ?string
    {
        if ($amount === null) {
            return null;
        }

        return number_format($amount, 2, ',', ' ');
    }
}
