<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

trait NormalizesIntegrationData
{
    protected function transformProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'external_id' => sprintf('product-%d', $product->id),
            'slug' => $product->slug,
            'title' => $product->title,
            'name' => $product->name,
            'description' => $product->description,
            'measurement_label' => $product->measurement_label,
            'measurement_value' => $product->measurement_value,
            'source_sheet' => $product->source_sheet,
            'source_row' => $product->source_row,
            'has_video' => $product->has_video,
            'video_label' => $product->video_label,
            'image_url' => $product->image_path ? asset($product->image_path) : null,
            'product_url' => route('products.show', $product),
            'price_from' => $product->price_from !== null ? (float) $product->price_from : null,
            'category' => [
                'id' => $product->category?->id,
                'name' => $product->category?->name,
                'slug' => $product->category?->slug,
                'parent' => $product->category?->parent ? [
                    'id' => $product->category->parent->id,
                    'name' => $product->category->parent->name,
                    'slug' => $product->category->parent->slug,
                ] : null,
            ],
            'prices' => $product->prices->map(fn ($price): array => [
                'column_index' => $price->column_index,
                'label' => $price->label,
                'display_value' => $price->display_value,
                'min_amount' => $price->min_amount !== null ? (float) $price->min_amount : null,
            ])->values()->all(),
            'updated_at' => $product->updated_at?->toAtomString(),
            'created_at' => $product->created_at?->toAtomString(),
        ];
    }

    protected function transformOrder(Order $order, bool $includeItems = true): array
    {
        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'payment_reference' => $order->payment_reference,
            'integration_reference' => $order->integration_reference,
            'integration_status' => $order->integration_status,
            'integration_last_error' => $order->integration_last_error,
            'price_profile_name' => $order->price_profile_name,
            'comment' => $order->comment,
            'customer' => [
                'id' => $order->user?->id,
                'name' => $order->user?->name,
                'company' => $order->user?->company,
                'login' => $order->user?->login,
                'email' => $order->user?->email,
            ],
            'totals' => [
                'items_count' => $order->items_count,
                'subtotal_amount' => $order->subtotal_amount !== null ? (float) $order->subtotal_amount : null,
                'total_amount' => $order->total_amount !== null ? (float) $order->total_amount : null,
                'paid_amount' => $order->paid_amount !== null ? (float) $order->paid_amount : null,
                'currency' => 'RUB',
            ],
            'placed_at' => $order->placed_at?->toAtomString(),
            'paid_at' => $order->paid_at?->toAtomString(),
            'integration_synced_at' => $order->integration_synced_at?->toAtomString(),
            'updated_at' => $order->updated_at?->toAtomString(),
            'items' => $includeItems
                ? $order->items->map(fn (OrderItem $item): array => $this->transformOrderItem($item))->values()->all()
                : [],
        ];
    }

    protected function transformOrderItem(OrderItem $item): array
    {
        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_title' => $item->product_title,
            'product_slug' => $item->product_slug,
            'product_url' => $item->product ? route('products.show', $item->product) : null,
            'image_url' => $item->product?->image_path ? asset($item->product->image_path) : null,
            'quantity' => $item->quantity,
            'price_label' => $item->price_label,
            'unit_price' => $item->unit_price !== null ? (float) $item->unit_price : null,
            'line_total' => $item->line_total !== null ? (float) $item->line_total : null,
            'source_sheet' => $item->source_sheet,
            'measurement_value' => $item->measurement_value,
        ];
    }
}
