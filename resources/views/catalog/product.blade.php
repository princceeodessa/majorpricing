@extends('layouts.app')

@section('title', $product->publicTitle().' - ПОТОЛКОВЫЧ')

@section('content')
    @php
        $showPrices = auth()->check();
        $price = $showPrices ? $product->publicPrice() : null;
        $comparePrice = $showPrices ? $product->comparePrice() : null;
        $category = $product->category;
        $unitLabel = $product->publicUnitLabel();
        $minimumSaleSummary = $product->minimumSaleQuantitySummary();
        $unitsInPackageSummary = $product->unitsInPackageSummary();
        $minCartQuantity = $product->cartQuantityMinimum();
        $stepCartQuantity = $product->cartQuantityStep();
        $maxCartQuantity = $product->cartQuantityMax();
        $safeCartQuantity = $cartQuantity > 0 ? $product->normalizeCartQuantity($cartQuantity) : $minCartQuantity;
        $availabilityLabel = $product->availabilityLabel();
        $availabilityTone = $product->availabilityTone();
        $fallbackImageUrl = asset('brand/product-placeholder.png');
        $galleryPaths = $product->galleryImagePaths();
        $galleryImages = collect($galleryPaths)
            ->filter(fn ($path): bool => filled($path))
            ->map(fn ($path): string => asset($path))
            ->values();
        if ($galleryImages->isEmpty()) {
            $galleryImages = collect([$fallbackImageUrl]);
        }
        $imageUrl = $galleryImages->first();
        $productFacts = collect([
            ['label' => 'В группе', 'value' => $category?->name],
            ['label' => 'Артикул', 'value' => $product->vendor_code],
            ['label' => 'Код', 'value' => $product->one_c_code],
            ['label' => 'Ед. изм.', 'value' => $unitLabel],
            ['label' => 'Бренд', 'value' => $product->brand_name],
            ['label' => 'Цвет', 'value' => $product->color_name],
            ['label' => 'Количество в упаковке', 'value' => $unitsInPackageSummary],
            ['label' => 'Наличие', 'value' => $availabilityLabel],
        ])->filter(fn (array $item) => filled($item['value']))->values();
    @endphp

    <section class="surface-card reveal-card p-5 sm:p-8">
        <div class="catalog-product-layout">
            <div
                class="catalog-product-gallery {{ $galleryImages->count() > 1 ? 'catalog-product-gallery--with-thumbs' : '' }}"
                data-gallery
                data-gallery-index="0"
            >
                @if ($galleryImages->count() > 1)
                    <div class="catalog-product-gallery__thumbs">
                        @foreach ($galleryImages as $index => $galleryImageUrl)
                            <button
                                type="button"
                                class="catalog-product-gallery__thumb {{ $index === 0 ? 'is-active' : '' }}"
                                data-gallery-thumb
                                data-gallery-image="{{ $galleryImageUrl }}"
                                data-gallery-alt="{{ $product->publicTitle() }}"
                                aria-label="Фото {{ $index + 1 }}"
                                aria-pressed="{{ $index === 0 ? 'true' : 'false' }}"
                            >
                                <img src="{{ $galleryImageUrl }}" alt="{{ $product->publicTitle() }} {{ $index + 1 }}">
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="catalog-product-stage">
                    @if ($galleryImages->count() > 1)
                        <button type="button" class="catalog-product-stage__arrow" data-gallery-prev aria-label="Предыдущее фото">‹</button>
                    @endif

                    <div class="catalog-product-stage__canvas">
                        <span class="catalog-product-stage__chip" data-gallery-chip>
                            {{ $category?->name ?? 'Каталог' }}
                        </span>
                        <div class="catalog-product-stage__halo"></div>

                        <div class="catalog-product-stage__image-frame">
                            <img
                                src="{{ $imageUrl }}"
                                alt="{{ $product->publicTitle() }}"
                                class="catalog-product-stage__image"
                                data-gallery-image
                                data-fallback-src="{{ $fallbackImageUrl }}"
                                onerror="this.onerror=null; this.src=this.dataset.fallbackSrc;"
                            >
                        </div>
                    </div>

                    @if ($galleryImages->count() > 1)
                        <button type="button" class="catalog-product-stage__arrow" data-gallery-next aria-label="Следующее фото">›</button>
                    @endif
                </div>
            </div>

            <div class="catalog-product-copy">
                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                    <a href="{{ route('catalog.index') }}" class="transition hover:text-slate-900">Каталог</a>
                    @if ($category)
                        <span>/</span>
                        <a href="{{ route('categories.show', $category) }}" class="transition hover:text-slate-900">{{ $category->name }}</a>
                    @endif
                </div>

                <h1 class="mt-5 max-w-3xl font-['IBM_Plex_Sans'] text-3xl font-semibold tracking-tight text-slate-950 sm:text-[2.55rem]">
                    {{ $product->publicTitle() }}
                </h1>

                @if ($product->brand_name)
                    <p class="catalog-product-copy__subtitle">{{ $product->brand_name }}</p>
                @elseif ($category)
                    <p class="catalog-product-copy__subtitle">{{ $category->name }}</p>
                @endif

                @if (filled($product->description))
                    <p class="catalog-product-copy__description">{{ $product->description }}</p>
                @endif

                <div class="catalog-product-price-block">
                    @if ($showPrices)
                        @if ($comparePrice?->min_amount !== null)
                            <p class="catalog-product-price-block__compare">
                                {{ \Illuminate\Support\Number::format((float) $comparePrice->min_amount, 2, locale: 'ru') }} ₽
                            </p>
                        @endif

                        @if ($price?->min_amount !== null)
                            <p class="catalog-product-price-block__value {{ $comparePrice?->min_amount !== null ? 'catalog-product-price-block__value--accent' : '' }}">
                                {{ \Illuminate\Support\Number::format((float) $price->min_amount, 2, locale: 'ru') }} ₽
                                @if ($unitLabel)
                                    <span>/ 1 {{ $unitLabel }}</span>
                                @endif
                            </p>
                        @else
                            <p class="catalog-product-price-block__value catalog-product-price-block__value--empty">Цена по запросу</p>
                        @endif

                        <p class="catalog-product-price-block__profile">{{ $price?->min_amount !== null ? 'Со скидкой' : 'Цена' }}</p>
                    @else
                        <p class="catalog-product-price-block__value catalog-product-price-block__value--empty">Цены доступны партнерам</p>
                        <p class="catalog-product-price-block__profile">Оставьте заявку, чтобы получить партнерский доступ.</p>
                    @endif
                </div>

                <p class="catalog-product-price-block__availability catalog-product-price-block__availability--{{ $availabilityTone }}">
                    {{ $availabilityLabel }}
                </p>

                @auth
                    <div class="catalog-product-secondary-actions">
                        @include('partials.favorite-toggle', ['product' => $product, 'showLabel' => true, 'sizeClass' => 'catalog-favorite-form--wide'])
                    </div>
                @endauth

                <div class="catalog-product-actions">
                    @auth
                        <div
                            class="catalog-product-card__cart-control"
                            data-cart-control
                            data-product-id="{{ $product->id }}"
                            data-quantity="{{ $cartQuantity > 0 ? $safeCartQuantity : 0 }}"
                            data-cart-unit-amount="{{ $price?->min_amount !== null ? (float) $price->min_amount : '' }}"
                            data-min-quantity="{{ $minCartQuantity }}"
                            data-step-quantity="{{ $stepCartQuantity }}"
                            data-store-url="{{ route('cart.store', $product) }}"
                            data-update-url="{{ route('cart.product.update', $product) }}"
                            data-destroy-url="{{ route('cart.product.destroy', $product) }}"
                            data-csrf-token="{{ csrf_token() }}"
                        >
                            <div class="{{ $cartQuantity > 0 ? 'hidden' : '' }}" data-cart-add-state>
                                <button type="button" class="catalog-buy-button" data-cart-add>В корзину</button>
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

                            <div
                                class="catalog-product-card__cart-total {{ $cartQuantity > 0 && $price?->min_amount !== null ? '' : 'hidden' }}"
                                data-cart-line-summary
                            >
                                <span>В корзине на</span>
                                <strong data-cart-line-amount>
                                    @if ($price?->min_amount !== null)
                                        {{ \Illuminate\Support\Number::format(round((float) $price->min_amount * $safeCartQuantity, 2), 2, locale: 'ru') }} ₽
                                    @endif
                                </strong>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('registration-requests.create') }}" class="catalog-buy-button">
                            Стать партнером
                        </a>
                    @endauth
                </div>

                @if ($productFacts->isNotEmpty())
                    <dl class="catalog-product-facts">
                        @foreach ($productFacts as $fact)
                            <div class="catalog-product-facts__item">
                                <dt>{{ $fact['label'] }}</dt>
                                <dd>{{ $fact['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
        </div>
    </section>
@endsection
