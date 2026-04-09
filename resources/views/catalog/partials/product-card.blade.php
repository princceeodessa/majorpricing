@php
    $price = $product->priceForProfile();
    $publicTitle = $product->publicTitle();
    $rootCategory = $product->category?->parent ?? $product->category;
    $detailLine = $product->description
        ? \Illuminate\Support\Str::limit(str_replace("\n", ' / ', $product->description), 108)
        : ($product->vendor_code ? 'Артикул: '.$product->vendor_code : ($product->brand_name ?: ''));
    $visualMark = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($publicTitle, 0, 2));
    $imageUrl = $product->image_path ? asset($product->image_path) : null;
    $productAccents = ['#d11117', '#c81e1e', '#b91c1c', '#991b1b', '#be123c', '#7f1d1d'];
    $productAccent = $productAccents[($rootCategory?->id ?? $product->id ?? 0) % count($productAccents)];
    $cartQuantity = (int) (($cartProductQuantities[$product->id] ?? 0));
@endphp

<article
    class="catalog-product-card reveal-card {{ $imageUrl ? 'has-image' : '' }}"
    style="animation-delay: {{ $delay ?? 0 }}ms; --card-accent: {{ $productAccent }};"
    data-product-card
    data-product-id="{{ $product->id }}"
>
    <div class="catalog-product-card__favorite">
        @include('partials.favorite-toggle', ['product' => $product])
    </div>

    <a href="{{ route('products.show', $product) }}" class="catalog-product-card__visual-link">
        <div class="catalog-product-card__visual">
            <div class="catalog-product-card__image-wrap {{ $imageUrl ? '' : 'is-placeholder' }}">
                @if ($imageUrl)
                    <img
                        src="{{ $imageUrl }}"
                        alt="{{ $publicTitle }}"
                        class="catalog-product-card__image"
                        loading="lazy"
                        decoding="async"
                        onerror="this.onerror=null; this.closest('.catalog-product-card__image-wrap').classList.add('is-fallback');"
                    >
                @endif

                <div class="catalog-product-card__mark">{{ $visualMark }}</div>
            </div>
        </div>
    </a>

    <div class="catalog-product-card__body">
        <div>
            <p class="catalog-product-card__eyebrow">{{ $product->category?->name ?? 'Раздел каталога' }}</p>
            <a href="{{ route('products.show', $product) }}" class="catalog-product-card__title-link">
                <h3 class="catalog-product-card__title">{{ $publicTitle }}</h3>
            </a>
            @if (filled($detailLine))
                <p class="catalog-product-card__description">{{ $detailLine }}</p>
            @endif
        </div>

        <div class="catalog-product-card__footer">
            <div>
                <p class="catalog-product-card__price-label">{{ $price?->label ?? 'Цена' }}</p>
                @if ($price?->min_amount !== null)
                    <p class="catalog-product-card__price">
                        {{ \Illuminate\Support\Number::format((float) $price->min_amount, 2, locale: 'ru') }} ₽
                    </p>
                @else
                    <p class="catalog-product-card__price catalog-product-card__price--empty">Цена по запросу</p>
                @endif
            </div>

            <div
                class="catalog-product-card__cart-control"
                data-cart-control
                data-product-id="{{ $product->id }}"
                data-quantity="{{ $cartQuantity }}"
                data-store-url="{{ route('cart.store', $product) }}"
                data-update-url="{{ route('cart.product.update', $product) }}"
                data-destroy-url="{{ route('cart.product.destroy', $product) }}"
                data-csrf-token="{{ csrf_token() }}"
            >
                <div class="{{ $cartQuantity > 0 ? 'hidden' : '' }}" data-cart-add-state>
                    <button type="button" class="catalog-product-card__cta" data-cart-add>В корзину</button>
                </div>

                <div class="catalog-product-card__stepper {{ $cartQuantity > 0 ? '' : 'hidden' }}" data-cart-qty-state>
                    <button type="button" class="catalog-product-card__stepper-btn" data-cart-dec aria-label="Уменьшить количество">−</button>
                    <span class="catalog-product-card__stepper-value" data-cart-quantity>{{ $cartQuantity }}</span>
                    <button type="button" class="catalog-product-card__stepper-btn" data-cart-inc aria-label="Увеличить количество">+</button>
                </div>
            </div>
        </div>
    </div>
</article>
