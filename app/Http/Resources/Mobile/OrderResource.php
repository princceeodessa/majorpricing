<?php

namespace App\Http\Resources\Mobile;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\MobileApi;
use App\Support\OrderStatuses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'status_label' => OrderStatuses::label($this->status),
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'items_count' => (int) $this->items_count,
            'subtotal_amount' => MobileApi::money($this->subtotal_amount),
            'total_amount' => MobileApi::money($this->total_amount),
            'currency' => 'RUB',
            'comment' => $this->comment,
            'manager_comment' => $this->manager_comment,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'customer' => [
                'name' => $this->customer_name,
                'company' => $this->customer_company,
                'email' => $this->customer_email,
                'phone' => $this->customer_phone,
                'contact_person' => $this->customer_contact_person,
                'telegram' => $this->customer_telegram,
                'delivery_address' => $this->customer_delivery_address,
            ],
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (OrderItem $item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_slug' => $item->product_slug,
                'name' => $item->product_title,
                'quantity' => (int) $item->quantity,
                'price_label' => $item->price_label,
                'unit_price' => MobileApi::money($item->unit_price),
                'line_total' => MobileApi::money($item->line_total),
                'unit' => $item->measurement_value,
            ])->values()->all()),
        ];
    }
}
