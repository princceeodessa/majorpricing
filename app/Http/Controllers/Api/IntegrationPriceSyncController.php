<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductPriceSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationPriceSyncController extends Controller
{
    public function __construct(
        private readonly ProductPriceSyncService $productPriceSyncService,
    ) {
    }

    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reset_missing' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:1000'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.external_id' => ['nullable', 'string', 'max:190'],
            'items.*.slug' => ['nullable', 'string', 'max:190'],
            'items.*.source_sheet' => ['nullable', 'string', 'max:190'],
            'items.*.source_row' => ['nullable', 'integer'],
            'items.*.price_from' => ['nullable', 'numeric', 'min:0'],
            'items.*.prices' => ['nullable', 'array'],
            'items.*.prices.*.column_index' => ['required_with:items.*.prices', 'integer', 'min:1', 'max:50'],
            'items.*.prices.*.label' => ['nullable', 'string', 'max:190'],
            'items.*.prices.*.display_value' => ['nullable', 'string', 'max:190'],
            'items.*.prices.*.min_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $result = $this->productPriceSyncService->sync(
            $validated['items'],
            (bool) ($validated['reset_missing'] ?? false),
        );

        return response()->json([
            'data' => $result,
            'integration' => [
                'service' => $request->attributes->get('integration_service'),
                'updated_at' => now()->toAtomString(),
            ],
        ]);
    }
}

