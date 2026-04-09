@extends('layouts.app')

@section('title', $product->publicTitle().' - MAJOR')

@section('content')
    @php
        $price = $product->priceForProfile();
        $category = $product->category;
        $unitLabel = $product->publicUnitLabel();
        $imageUrl = $product->image_path ? asset($product->image_path) : null;
        $productFacts = collect([
            ['label' => 'В группе', 'value' => $category?->name],
            ['label' => 'Артикул', 'value' => $product->vendor_code],
            ['label' => 'Код', 'value' => $product->one_c_code],
            ['label' => 'Ед. изм.', 'value' => $unitLabel],
            ['label' => 'Бренд', 'value' => $product->brand_name],
        ])->filter(fn (array $item) => filled($item['value']))->values();
        $description = trim((string) $product->description);
    @endphp

    <section class="surface-card reveal-card p-5 sm:p-8">
        <div class="catalog-product-layout">
            <div class="catalog-product-gallery">
                <div class="catalog-product-stage">
                    <div class="catalog-product-stage__canvas">
                        <span class="catalog-product-stage__chip">
                            {{ $category?->name ?? 'Каталог' }}
                        </span>
                        <div class="catalog-product-stage__halo"></div>

                        @if ($imageUrl)
                            <div class="catalog-product-stage__image-frame">
                                <img src="{{ $imageUrl }}" alt="{{ $product->publicTitle() }}" class="catalog-product-stage__image">
                            </div>
                        @else
                            <div class="catalog-product-stage__mark">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($product->publicTitle(), 0, 2)) }}</div>
                        @endif
                    </div>
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

                <div class="catalog-product-price-block">
                    @if ($price?->min_amount !== null)
                        <p class="catalog-product-price-block__value">
                            {{ \Illuminate\Support\Number::format((float) $price->min_amount, 2, locale: 'ru') }} ₽
                            @if ($unitLabel)
                                <span>/ 1 {{ $unitLabel }}</span>
                            @endif
                        </p>
                    @else
                        <p class="catalog-product-price-block__value catalog-product-price-block__value--empty">Цена по запросу</p>
                    @endif

                    <p class="catalog-product-price-block__profile">{{ $price?->label ?? 'Цена' }}</p>
                </div>

                <div class="catalog-product-secondary-actions">
                    @include('partials.favorite-toggle', ['product' => $product, 'showLabel' => true, 'sizeClass' => 'catalog-favorite-form--wide'])
                </div>

                <form action="{{ route('cart.store', $product) }}" method="POST" class="catalog-product-actions">
                    @csrf

                    <div class="catalog-qty-control" data-qty>
                        <button type="button" data-qty-dec aria-label="Уменьшить количество">−</button>
                        <input type="number" min="1" max="999" value="1" name="quantity" data-qty-input>
                        <button type="button" data-qty-inc aria-label="Увеличить количество">+</button>
                    </div>

                    <button type="submit" class="catalog-buy-button">В корзину</button>
                </form>

                @if (filled($description))
                    <p class="catalog-product-copy__description">{{ $description }}</p>
                @endif

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
