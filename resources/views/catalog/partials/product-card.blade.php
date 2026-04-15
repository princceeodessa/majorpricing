@php
    $price = $product->publicPrice();
    $comparePrice = $product->comparePrice();
    $publicTitle = $product->publicTitle();
    $rootCategory = $product->category?->parent ?? $product->category;
    $fallbackImageUrl = asset('brand/product-placeholder.png');
    $hasRealImage = filled($product->image_path);
    $imageUrl = $hasRealImage ? asset($product->image_path) : $fallbackImageUrl;
    $unitLabel = $product->publicUnitLabel();
    $availabilityLabel = $product->availabilityLabel();
    $availabilityTone = $product->availabilityTone();
    $productAccents = ['#163459', '#1f4f7a', '#255f91', '#2f6f9f', '#3c7fae', '#0f2947'];
    $productAccent = $productAccents[($rootCategory?->id ?? $product->id ?? 0) % count($productAccents)];
    $cartQuantity = (int) (($cartProductQuantities[$product->id] ?? 0));
@endphp

<article
    class="catalog-product-card reveal-card {{ $hasRealImage ? 'has-image' : '' }}"
    style="animation-delay: {{ $delay ?? 0 }}ms; --card-accent: {{ $productAccent }};"
    data-product-card
    data-product-id="{{ $product->id }}"
>
    <div class="catalog-product-card__favorite">
        @include('partials.favorite-toggle', ['product' => $product])
    </div>

    <a href="{{ route('products.show', $product) }}" class="catalog-product-card__visual-link">
        <div class="catalog-product-card__visual">
            <div class="catalog-product-card__image-wrap">
                <img
                    src="{{ $imageUrl }}"
                    alt="{{ $publicTitle }}"
                    class="catalog-product-card__image"
                    loading="lazy"
                    decoding="async"
                    data-fallback-src="{{ $fallbackImageUrl }}"
                    onerror="this.onerror=null; this.src=this.dataset.fallbackSrc;"
                >
            </div>
        </div>
    </a>

    <div class="catalog-product-card__body">
        <div>
            <p class="catalog-product-card__eyebrow">{{ $product->category?->name ?? 'Раздел каталога' }}</p>
            <a href="{{ route('products.show', $product) }}" class="catalog-product-card__title-link">
                <h3 class="catalog-product-card__title">{{ $publicTitle }}</h3>
            </a>
            <p class="catalog-product-card__meta catalog-product-card__meta--{{ $availabilityTone }}">
                {{ $availabilityLabel }}
            </p>
        </div>

        <div class="catalog-product-card__footer">
            <div>
                <p class="catalog-product-card__price-label">{{ $price?->min_amount !== null ? 'Со скидкой' : 'Цена' }}</p>
                @if ($comparePrice?->min_amount !== null)
                    <p class="catalog-product-card__compare-price">
                        {{ \Illuminate\Support\Number::format((float) $comparePrice->min_amount, 2, locale: 'ru') }} ₽
                    </p>
                @endif
                @if ($price?->min_amount !== null)
                    <p class="catalog-product-card__price {{ $comparePrice?->min_amount !== null ? 'catalog-product-card__price--accent' : '' }}">
                        {{ \Illuminate\Support\Number::format((float) $price->min_amount, 2, locale: 'ru') }} ₽
                        @if ($unitLabel)
                            <span class="catalog-product-card__price-unit">/ за 1 {{ mb_strtolower($unitLabel) }}</span>
                        @endif
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
