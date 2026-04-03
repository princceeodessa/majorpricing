<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ErpOrderSyncService
{
    public function isConfigured(): bool
    {
        return filled(config('integrations.erp.orders_push_url'));
    }

    public function push(Order $order): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $order->loadMissing(['user.priceProfile', 'items.product.category.parent']);

        $request = Http::acceptJson()
            ->timeout((int) config('integrations.erp.timeout', 10))
            ->connectTimeout((int) config('integrations.erp.connect_timeout', 5));

        if (filled(config('integrations.erp.outgoing_token'))) {
            $request = $request->withHeaders([
                (string) config('integrations.erp.outgoing_token_header', 'X-Integration-Key') => (string) config('integrations.erp.outgoing_token'),
            ]);
        }

        try {
            $response = $request->post(
                (string) config('integrations.erp.orders_push_url'),
                $this->buildPayload($order),
            );

            if (! $response->successful()) {
                throw new \RuntimeException('ERP order sync failed with status '.$response->status().'.');
            }

            $payload = $response->json();

            $order->forceFill([
                'integration_status' => 'synced',
                'integration_last_error' => null,
                'integration_synced_at' => now(),
                'integration_reference' => $payload['integration_reference']
                    ?? data_get($payload, 'data.integration_reference')
                    ?? $order->integration_reference,
            ])->save();

            return true;
        } catch (Throwable $exception) {
            report($exception);

            $order->forceFill([
                'integration_status' => 'failed',
                'integration_last_error' => Str::limit($exception->getMessage(), 2000),
            ])->save();

            return false;
        }
    }

    public function buildPayload(Order $order): array
    {
        return [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'payment_reference' => $order->payment_reference,
                'placed_at' => $order->placed_at?->toAtomString(),
                'comment' => $order->comment,
                'price_profile_name' => $order->price_profile_name,
                'items_count' => $order->items_count,
                'subtotal_amount' => $order->subtotal_amount !== null ? (float) $order->subtotal_amount : null,
                'total_amount' => $order->total_amount !== null ? (float) $order->total_amount : null,
                'currency' => 'RUB',
            ],
            'customer' => [
                'id' => $order->user?->id,
                'name' => $order->user?->name,
                'company' => $order->user?->company,
                'login' => $order->user?->login,
                'email' => $order->user?->email,
                'price_profile' => $order->user?->priceProfile?->name,
            ],
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'external_id' => $item->product_id ? sprintf('product-%d', $item->product_id) : null,
                'product_title' => $item->product_title,
                'product_slug' => $item->product_slug,
                'quantity' => $item->quantity,
                'price_label' => $item->price_label,
                'unit_price' => $item->unit_price !== null ? (float) $item->unit_price : null,
                'line_total' => $item->line_total !== null ? (float) $item->line_total : null,
                'source_sheet' => $item->source_sheet,
                'measurement_value' => $item->measurement_value,
            ])->values()->all(),
        ];
    }
}

