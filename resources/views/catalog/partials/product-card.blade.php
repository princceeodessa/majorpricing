@php
    $profile = auth()->user()?->priceProfile;
    $price = $product->priceForProfile($profile);
    $rootCategory = $product->category?->parent ?? $product->category;
    $detailLine = $product->description
        ? \Illuminate\Support\Str::limit(str_replace("\n", ' / ', $product->description), 108)
        : ($product->measurement_value ? \Illuminate\Support\Str::limit(str_replace("\n", ' / ', $product->measurement_value), 78) : 'Подробная спецификация открывается внутри карточки.');
    $visualMark = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($product->title, 0, 2));
    $imageUrl = $product->image_path ? asset($product->image_path) : null;
    $productAccents = ['#d11117', '#c81e1e', '#b91c1c', '#991b1b', '#be123c', '#7f1d1d'];
    $productAccent = $productAccents[($rootCategory?->id ?? $product->id ?? 0) % count($productAccents)];
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
                        alt="{{ $product->title }}"
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
                <h3 class="catalog-product-card__title">{{ $product->title }}</h3>
            </a>
            <p class="catalog-product-card__description">{{ $detailLine }}</p>
        </div>

        <div class="catalog-product-card__footer">
            <div>
                <p class="catalog-product-card__price-label">{{ $profile?->name ?? 'Базовый прайс' }}</p>
                @if ($price?->min_amount !== null)
                    <p class="catalog-product-card__price">
                        {{ \Illuminate\Support\Number::format((float) $price->min_amount, 2, locale: 'ru') }} ₽
                    </p>
                @else
                    <p class="catalog-product-card__price catalog-product-card__price--empty">Цена по запросу</p>
                @endif
            </div>

            <form action="{{ route('cart.store', $product) }}" method="POST" class="catalog-product-card__cart-form">
                @csrf
                <button type="submit" class="catalog-product-card__cta">В корзину</button>
            </form>
        </div>
    </div>
</article>
