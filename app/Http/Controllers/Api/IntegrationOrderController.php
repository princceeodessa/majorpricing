<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\NormalizesIntegrationData;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationOrderController extends Controller
{
    use NormalizesIntegrationData;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'updated_since' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:80'],
            'payment_status' => ['nullable', 'string', 'max:80'],
        ]);

        $orders = Order::query()
            ->with(['user', 'items.product'])
            ->when(
                filled($validated['updated_since'] ?? null),
                fn ($query) => $query->where('updated_at', '>=', $validated['updated_since']),
            )
            ->when(
                filled($validated['status'] ?? null),
                fn ($query) => $query->where('status', $validated['status']),
            )
            ->when(
                filled($validated['payment_status'] ?? null),
                fn ($query) => $query->where('payment_status', $validated['payment_status']),
            )
            ->latest('placed_at')
            ->latest('id')
            ->paginate((int) ($validated['per_page'] ?? 50))
            ->withQueryString();

        return response()->json([
            'data' => $orders->getCollection()->map(fn (Order $order): array => $this->transformOrder($order))->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
                'next_page_url' => $orders->nextPageUrl(),
                'prev_page_url' => $orders->previousPageUrl(),
            ],
            'integration' => [
                'service' => $request->attributes->get('integration_service'),
                'generated_at' => now()->toAtomString(),
            ],
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $order->loadMissing(['user', 'items.product']);

        return response()->json([
            'data' => $this->transformOrder($order),
            'integration' => [
                'service' => $request->attributes->get('integration_service'),
                'generated_at' => now()->toAtomString(),
            ],
        ]);
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:80'],
            'payment_status' => ['nullable', 'string', 'max:80'],
            'payment_method' => ['nullable', 'string', 'max:120'],
            'payment_reference' => ['nullable', 'string', 'max:190'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_at' => ['nullable', 'date'],
            'integration_reference' => ['nullable', 'string', 'max:190'],
            'integration_synced_at' => ['nullable', 'date'],
            'payment_payload' => ['nullable', 'array'],
            'comment' => ['nullable', 'string', 'max:1500'],
        ]);

        if (($validated['payment_status'] ?? null) === 'paid' && ! array_key_exists('paid_at', $validated)) {
            $validated['paid_at'] = now();
        }

        if (filled($validated['integration_reference'] ?? null) && ! array_key_exists('integration_synced_at', $validated)) {
            $validated['integration_synced_at'] = now();
        }

        $order->fill($validated);
        $order->save();
        $order->loadMissing(['user', 'items.product']);

        return response()->json([
            'data' => $this->transformOrder($order),
            'integration' => [
                'service' => $request->attributes->get('integration_service'),
                'updated_at' => now()->toAtomString(),
            ],
        ]);
    }
}
