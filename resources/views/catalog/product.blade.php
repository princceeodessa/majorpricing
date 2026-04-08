@extends('layouts.app')

@section('title', $product->title.' - MAJOR')

@section('content')
    @php
        $price = $product->priceForProfile();
        $category = $product->category;
        $rootCategory = $category?->parent ?? $category;
        $galleryFacts = collect([
            $rootCategory?->name,
            $category?->name,
            $product->measurement_value ? str_replace("\n", ' / ', $product->measurement_value) : null,
        ])->filter(fn ($value) => filled($value))->unique()->values()->take(4);
        $stageMetaFallback = $product->measurement_value ? str_replace("\n", ' / ', $product->measurement_value) : 'Каталог';
        $gallerySlides = ($galleryFacts->isNotEmpty() ? $galleryFacts : collect([$rootCategory?->name ?? $category?->name ?? 'MAJOR']))
            ->values()
            ->map(fn ($fact) => [
                'thumb' => \Illuminate\Support\Str::limit($fact, 12),
                'chip' => \Illuminate\Support\Str::limit($fact, 18),
                'title' => \Illuminate\Support\Str::limit($product->title, 46),
                'meta' => \Illuminate\Support\Str::limit($fact, 58),
            ]);
        $productFacts = collect([
            ['label' => 'Категория', 'value' => $category?->name],
            ['label' => 'Направление', 'value' => $rootCategory && $rootCategory?->id !== $category?->id ? $rootCategory->name : null],
            ['label' => $product->measurement_label ?? 'Параметры', 'value' => $product->measurement_value ? str_replace("\n", ' / ', $product->measurement_value) : null],
            ['label' => 'Видео', 'value' => $product->has_video ? ($product->video_label ?: 'Есть видео по товару') : null],
        ])->filter(fn (array $item) => filled($item['value']))->values();
        $description = trim($product->description ?: $product->name);
        $imageUrl = $product->image_path ? asset($product->image_path) : null;
    @endphp

    <section class="surface-card reveal-card p-5 sm:p-8">
        <div class="catalog-product-layout">
            <div class="catalog-product-gallery" data-gallery data-gallery-index="0">
                <div class="catalog-product-gallery__thumbs">
                    @foreach ($gallerySlides as $index => $slide)
                        <button
                            type="button"
                            class="catalog-product-thumb {{ $index === 0 ? 'is-active' : '' }}"
                            data-gallery-thumb
                            data-gallery-index="{{ $index }}"
                            data-gallery-chip="{{ $slide['chip'] }}"
                            data-gallery-title="{{ $slide['title'] }}"
                            data-gallery-meta="{{ $slide['meta'] }}"
                            aria-pressed="{{ $index === 0 ? 'true' : 'false' }}"
                        >
                            {{ $slide['thumb'] }}
                        </button>
                    @endforeach
                </div>

                <div class="catalog-product-stage">
                    <button
                        type="button"
                        class="catalog-product-stage__arrow is-left"
                        aria-label="Предыдущий слайд"
                        data-gallery-prev
                        @disabled($gallerySlides->count() < 2)
                    >‹</button>

                    <div class="catalog-product-stage__canvas">
                        <span class="catalog-product-stage__chip" data-gallery-chip>
                            {{ $gallerySlides->first()['chip'] ?? \Illuminate\Support\Str::limit($rootCategory?->name ?? $category?->name ?? 'Каталог', 18) }}
                        </span>
                        <div class="catalog-product-stage__halo"></div>
                        @if ($imageUrl)
                            <div class="catalog-product-stage__image-frame">
                                <img src="{{ $imageUrl }}" alt="{{ $product->title }}" class="catalog-product-stage__image">
                            </div>
                        @else
                            <div class="catalog-product-stage__mark">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($product->title, 0, 2)) }}</div>
                        @endif
                        <p class="catalog-product-stage__title" data-gallery-title>
                            {{ $gallerySlides->first()['title'] ?? \Illuminate\Support\Str::limit($product->title, 46) }}
                        </p>
                        <p class="catalog-product-stage__meta" data-gallery-meta>
                            {{ $gallerySlides->first()['meta'] ?? \Illuminate\Support\Str::limit($stageMetaFallback, 58) }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="catalog-product-stage__arrow is-right"
                        aria-label="Следующий слайд"
                        data-gallery-next
                        @disabled($gallerySlides->count() < 2)
                    >›</button>
                </div>
            </div>

            <div class="catalog-product-copy">
                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                    <a href="{{ route('catalog.index') }}" class="transition hover:text-slate-900">Каталог</a>
                    @if ($rootCategory)
                        <span>/</span>
                        <a href="{{ route('categories.show', $rootCategory) }}" class="transition hover:text-slate-900">{{ $rootCategory->name }}</a>
                    @endif
                    @if ($category && $rootCategory?->id !== $category->id)
                        <span>/</span>
                        <a href="{{ route('categories.show', $category) }}" class="transition hover:text-slate-900">{{ $category->name }}</a>
                    @endif
                </div>

                <h1 class="mt-5 max-w-3xl font-['IBM_Plex_Sans'] text-3xl font-semibold tracking-tight text-slate-950 sm:text-[2.55rem]">
                    {{ $product->title }}
                </h1>

                <p class="catalog-product-copy__subtitle">
                    {{ $category?->name ?? ($rootCategory?->name ?? 'Каталог') }}
                </p>

                <div class="catalog-product-price-block">
                    @if ($price?->min_amount !== null)
                        <p class="catalog-product-price-block__value">
                            {{ \Illuminate\Support\Number::format((float) $price->min_amount, 2, locale: 'ru') }} ₽
                            <span>/ 1 шт</span>
                        </p>
                    @else
                        <p class="catalog-product-price-block__value catalog-product-price-block__value--empty">Цена по запросу</p>
                    @endif

                    <p class="catalog-product-price-block__profile">Цена</p>
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

                <p class="catalog-product-copy__hint">
                    Заявочный режим остаётся закрытым: сначала позиция попадает в корзину, затем вы подтверждаете заказ в кабинете.
                    @if (($cartQuantity ?? 0) > 0)
                        Сейчас в корзине уже {{ $cartQuantity }} шт.
                    @endif
                </p>

                <p class="catalog-product-copy__description">{{ $description }}</p>

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
