@php
    $profile = auth()->user()?->priceProfile;
    $price = $product->priceForProfile($profile);
    $rootCategory = $product->category?->parent ?? $product->category;
    $detailLine = $product->description
        ? \Illuminate\Support\Str::limit(str_replace("\n", ' / ', $product->description), 108)
        : ($product->measurement_value ? \Illuminate\Support\Str::limit(str_replace("\n", ' / ', $product->measurement_value), 78) : 'Подробная спецификация открывается внутри карточки.');
    $visualCaption = $product->source_sheet ?: ($product->measurement_value ? str_replace("\n", ' / ', $product->measurement_value) : 'Позиция каталога');
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
            <span class="catalog-product-card__badge">{{ \Illuminate\Support\Str::limit($rootCategory?->name ?? $product->category?->name ?? 'Каталог', 18) }}</span>
            @if ($imageUrl)
                <div class="catalog-product-card__image-wrap">
                    <img src="{{ $imageUrl }}" alt="{{ $product->title }}" class="catalog-product-card__image">
                </div>
            @else
                <div class="catalog-product-card__mark">{{ $visualMark }}</div>
            @endif
            <p class="catalog-product-card__caption">{{ \Illuminate\Support\Str::limit($visualCaption, 34) }}</p>
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
