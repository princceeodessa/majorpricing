<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class MobileCartService
{
    public const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';

    public const PAYMENT_METHOD_CASH = 'cash';

    /**
     * @return array{0: EloquentCollection<int, CartItem>, 1: array{items_count:int,total_quantity:int,total_amount:float,base_total_amount:float,discount_total_amount:float,payment_method:string,priced_items_count:int,unpriced_items_count:int}}
     */
    public function resolve(User $user, ?string $paymentMethod = null): array
    {
        $paymentMethod = $this->normalizePaymentMethod($paymentMethod);

        /** @var EloquentCollection<int, CartItem> $cartItems */
        $cartItems = CartItem::query()
            ->with(['product.category.parent', 'product.prices', 'product.productImages'])
            ->whereHas('product', fn ($query) => $query->visibleInCatalog())
            ->where('user_id', $user->id)
            ->latest('id')
            ->get();

        $cartItems->each(function (CartItem $cartItem) use ($paymentMethod): void {
            $product = $cartItem->product;

            if ($product) {
                $normalizedQuantity = $product->normalizeCartQuantity((int) $cartItem->quantity);

                if ($normalizedQuantity !== (int) $cartItem->quantity) {
                    $cartItem->quantity = $normalizedQuantity;
                    $cartItem->save();
                }
            }

            $discountPrice = $product?->publicPrice();
            $basePrice = $product?->priceForPaymentMethod(self::PAYMENT_METHOD_BANK_TRANSFER);
            $resolvedPrice = $product?->priceForPaymentMethod($paymentMethod);

            $discountUnitAmount = $discountPrice?->min_amount !== null ? (float) $discountPrice->min_amount : null;
            $baseUnitAmount = $basePrice?->min_amount !== null ? (float) $basePrice->min_amount : null;
            $resolvedUnitAmount = $resolvedPrice?->min_amount !== null ? (float) $resolvedPrice->min_amount : null;

            $cartItem->setAttribute('discount_price', $discountPrice);
            $cartItem->setAttribute('base_price', $basePrice);
            $cartItem->setAttribute('resolved_price', $resolvedPrice);
            $cartItem->setAttribute('discount_unit_amount', $discountUnitAmount);
            $cartItem->setAttribute('base_unit_amount', $baseUnitAmount);
            $cartItem->setAttribute('resolved_unit_amount', $resolvedUnitAmount);
            $cartItem->setAttribute('discount_line_amount', $discountUnitAmount !== null ? round($discountUnitAmount * $cartItem->quantity, 2) : null);
            $cartItem->setAttribute('base_line_amount', $baseUnitAmount !== null ? round($baseUnitAmount * $cartItem->quantity, 2) : null);
            $cartItem->setAttribute('resolved_line_amount', $resolvedUnitAmount !== null ? round($resolvedUnitAmount * $cartItem->quantity, 2) : null);
        });

        return [
            $cartItems,
            [
                'items_count' => $cartItems->count(),
                'total_quantity' => (int) $cartItems->sum('quantity'),
                'total_amount' => round((float) $cartItems->sum(fn (CartItem $item): float => (float) ($item->getAttribute('resolved_line_amount') ?? 0)), 2),
                'base_total_amount' => round((float) $cartItems->sum(fn (CartItem $item): float => (float) ($item->getAttribute('base_line_amount') ?? 0)), 2),
                'discount_total_amount' => round((float) $cartItems->sum(fn (CartItem $item): float => (float) ($item->getAttribute('discount_line_amount') ?? 0)), 2),
                'payment_method' => $paymentMethod,
                'priced_items_count' => $cartItems->filter(fn (CartItem $item): bool => $item->getAttribute('resolved_line_amount') !== null)->count(),
                'unpriced_items_count' => $cartItems->filter(fn (CartItem $item): bool => $item->getAttribute('resolved_line_amount') === null)->count(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function paymentMethods(): array
    {
        return [
            self::PAYMENT_METHOD_BANK_TRANSFER => 'Безналичный расчет',
            self::PAYMENT_METHOD_CASH => 'Наличный расчет',
        ];
    }

    public function normalizePaymentMethod(?string $paymentMethod): string
    {
        return array_key_exists((string) $paymentMethod, $this->paymentMethods())
            ? (string) $paymentMethod
            : self::PAYMENT_METHOD_BANK_TRANSFER;
    }
}
