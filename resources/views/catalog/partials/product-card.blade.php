@php
    $price = $product->publicPrice();
    $comparePrice = $product->comparePrice();
    $publicTitle = $product->publicTitle();
    $rootCategory = $product->category?->parent ?? $product->category;
    $fallbackImageUrl = asset('brand/product-placeholder.png');
    $galleryPaths = $product->galleryImagePaths();
    $hasRealImage = collect($galleryPaths)->contains(fn ($path): bool => filled($path));
    $galleryImages = collect($galleryPaths)
        ->filter(fn ($path): bool => filled($path))
        ->map(fn ($path): string => asset($path))
        ->values();
    if ($galleryImages->isEmpty()) {
        $galleryImages = collect([$fallbackImageUrl]);
    }
    $imageUrl = $galleryImages->first();
    $unitLabel = $product->publicUnitLabel();
    $availabilityLabel = $product->availabilityLabel();
    $availabilityTone = $product->availabilityTone();
    $productAccents = ['#163459', '#1f4f7a', '#255f91', '#2f6f9f', '#3c7fae', '#0f2947'];
    $productAccent = $productAccents[($rootCategory?->id ?? $product->id ?? 0) % count($productAccents)];
    $cartQuantity = (int) (($cartProductQuantities[$product->id] ?? 0));
    $minCartQuantity = $product->cartQuantityMinimum();
    $stepCartQuantity = $product->cartQuantityStep();
    $maxCartQuantity = $product->cartQuantityMax();
    $safeCartQuantity = $cartQuantity > 0 ? $product->normalizeCartQuantity($cartQuantity) : $minCartQuantity;
    $unitsInPackageSummary = $product->unitsInPackageSummary();
    $galleryPayload = [
        'productUrl' => route('products.show', $product),
        'title' => $publicTitle,
        'fallbackImageUrl' => $fallbackImageUrl,
        'images' => $galleryImages->values()->all(),
    ];
    $cartPayload = [
        'productId' => $product->id,
        'quantity' => $cartQuantity > 0 ? $safeCartQuantity : 0,
        'minQuantity' => $minCartQuantity,
        'stepQuantity' => $stepCartQuantity,
        'maxQuantity' => $maxCartQuantity,
        'storeUrl' => route('cart.store', $product),
        'updateUrl' => route('cart.product.update', $product),
        'destroyUrl' => route('cart.product.destroy', $product),
        'csrfToken' => csrf_token(),
        'unitsInPackageSummary' => $unitsInPackageSummary,
        'colorName' => filled($product->color_name) ? $product->color_name : null,
    ];
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

    <div
        class="catalog-product-card__visual"
        data-vue-gallery
        data-vue-gallery-props='@json($galleryPayload, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)'
    >
        <a href="{{ route('products.show', $product) }}" class="catalog-product-card__visual-link">
            <div class="catalog-product-card__image-wrap">
                <img
                    src="{{ $imageUrl }}"
                    alt="{{ $publicTitle }}"
                    class="catalog-product-card__image"
                    data-gallery-image
                    loading="lazy"
                    decoding="async"
                    data-fallback-src="{{ $fallbackImageUrl }}"
                    onerror="this.onerror=null; this.src=this.dataset.fallbackSrc;"
                >
            </div>
        </a>

        @if ($galleryImages->count() > 1)
            <button type="button" class="catalog-product-card__gallery-arrow is-prev" data-gallery-prev aria-label="Предыдущее фото">‹</button>
            <button type="button" class="catalog-product-card__gallery-arrow is-next" data-gallery-next aria-label="Следующее фото">›</button>

            <div class="catalog-product-card__gallery-dots">
                @foreach ($galleryImages as $index => $galleryImageUrl)
                    <button
                        type="button"
                        class="catalog-product-card__gallery-dot {{ $index === 0 ? 'is-active' : '' }}"
                        data-gallery-thumb
                        data-gallery-image="{{ $galleryImageUrl }}"
                        data-gallery-alt="{{ $publicTitle }}"
                        aria-label="Фото {{ $index + 1 }}"
                        aria-pressed="{{ $index === 0 ? 'true' : 'false' }}"
                    ></button>
                @endforeach
            </div>
        @endif
    </div>

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
                data-vue-cart-control
                data-vue-cart-props='@json($cartPayload, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)'
            >
                <div class="{{ $cartQuantity > 0 ? 'hidden' : '' }}" data-cart-add-state>
                    <button type="button" class="catalog-product-card__cta" data-cart-add>В корзину</button>
                </div>

                <div class="catalog-product-card__stepper {{ $cartQuantity > 0 ? '' : 'hidden' }}" data-cart-qty-state>
                    <button type="button" class="catalog-product-card__stepper-btn" data-cart-dec aria-label="Уменьшить количество">−</button>
                    <input
                        type="number"
                        min="{{ $minCartQuantity }}"
                        max="{{ $maxCartQuantity }}"
                        step="{{ $stepCartQuantity }}"
                        inputmode="numeric"
                        class="catalog-product-card__stepper-value"
                        data-cart-quantity
                        data-cart-quantity-input
                        value="{{ $safeCartQuantity }}"
                        aria-label="Quantity"
                        title="Enter quantity manually"
                    >
                    <button type="button" class="catalog-product-card__stepper-btn" data-cart-inc aria-label="Увеличить количество">+</button>
                </div>

                @if ($unitsInPackageSummary)
                    <p class="catalog-product-card__meta">
                        Упаковка: {{ $unitsInPackageSummary }}
                    </p>
                @endif

                @if (filled($product->color_name))
                    <p class="catalog-product-card__meta">Цвет: {{ $product->color_name }}</p>
                @endif
            </div>
        </div>
    </div>
</article>
