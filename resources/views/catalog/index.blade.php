@extends('layouts.app')

@section('title', 'Каталог MAJOR')

@section('content')
    <section class="catalog-spotlight" data-home-banners data-home-banner-interval="7600">
        <button type="button" class="catalog-spotlight__arrow is-left" data-home-banner-prev aria-label="Предыдущий баннер">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M14.5 5 8 12l6.5 7" />
            </svg>
        </button>

        <div class="catalog-spotlight__viewport">
            <article class="catalog-spotlight__slide is-active" data-home-banner-slide aria-hidden="false">
                <img
                    src="{{ asset('brand/major-banner-delivery.png') }}"
                    alt="Доставка по городу"
                    class="catalog-spotlight__image"
                    loading="eager"
                    decoding="async"
                >
            </article>

            <article class="catalog-spotlight__slide" data-home-banner-slide aria-hidden="true">
                <img
                    src="{{ asset('brand/major-banner-catalog.png') }}"
                    alt="Комплектующие для натяжных потолков"
                    class="catalog-spotlight__image"
                    loading="lazy"
                    decoding="async"
                >
            </article>
        </div>

        <button type="button" class="catalog-spotlight__arrow is-right" data-home-banner-next aria-label="Следующий баннер">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="m9.5 5 6.5 7-6.5 7" />
            </svg>
        </button>

        <div class="catalog-spotlight__footer">
            <div class="catalog-spotlight__progress">
                <span data-home-banner-progress></span>
            </div>

            <div class="catalog-spotlight__dots">
                <button type="button" class="catalog-spotlight__dot is-active" data-home-banner-dot="0" aria-label="Баннер 1" aria-pressed="true"></button>
                <button type="button" class="catalog-spotlight__dot" data-home-banner-dot="1" aria-label="Баннер 2" aria-pressed="false"></button>
            </div>
        </div>
    </section>

    @if ($rootCategories->isNotEmpty())
        <section class="mt-8">
            <div class="catalog-section-head catalog-section-head--clean">
                <h2 class="catalog-section-title">Основные категории</h2>
            </div>

            <div class="catalog-category-showcase mt-5" data-category-rail>
                <button type="button" class="catalog-category-showcase__arrow is-left" data-category-rail-prev aria-label="Прокрутить категории влево">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M14.5 5 8 12l6.5 7" />
                    </svg>
                </button>

                <div class="catalog-category-showcase__viewport" data-category-rail-track>
                    <div class="catalog-category-showcase__track">
                        @foreach ($rootCategories as $index => $category)
                            @php($previewImage = $category->catalog_preview_image ? asset($category->catalog_preview_image) : null)

                            <a
                                href="{{ route('categories.show', $category) }}"
                                class="catalog-category-card reveal-card"
                                style="animation-delay: {{ $index * 70 }}ms; --category-accent: {{ $category->accent_color ?? '#d11117' }};"
                            >
                                <div class="catalog-category-card__copy">
                                    <div class="catalog-category-card__head">
                                        <span class="catalog-category-card__badge">{{ $category->children->count() }} секц.</span>
                                    </div>

                                    <h3 class="catalog-category-card__title">{{ $category->name }}</h3>
                                    <p class="catalog-category-card__meta">{{ $category->catalog_products_count }} товаров в наличии</p>
                                </div>

                                <div class="catalog-category-card__visual">
                                    <div class="catalog-category-card__media">
                                        @if ($previewImage)
                                            <img src="{{ $previewImage }}" alt="{{ $category->catalog_preview_title ?? $category->name }}" class="catalog-category-card__image">
                                        @else
                                            <div class="catalog-category-card__mark">
                                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($category->name, 0, 2)) }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>

                <button type="button" class="catalog-category-showcase__arrow is-right" data-category-rail-next aria-label="Прокрутить категории вправо">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="m9.5 5 6.5 7-6.5 7" />
                    </svg>
                </button>
            </div>
        </section>
    @endif

    @if ($featuredProducts->isNotEmpty())
        <section class="mt-10">
            <div class="catalog-section-head catalog-section-head--clean">
                <h2 class="catalog-section-title">Новинки</h2>
            </div>

            <div class="catalog-grid mt-5">
                @foreach ($featuredProducts->take(4) as $index => $product)
                    @include('catalog.partials.product-card', ['product' => $product, 'delay' => $index * 90])
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-10">
        <div class="catalog-section-head catalog-section-head--clean">
            <h2 class="catalog-section-title">Каталог товаров</h2>
        </div>

        @if ($products->isEmpty())
            <div class="surface-card mt-5 p-10 text-center">
                <h3 class="font-['IBM_Plex_Sans'] text-2xl font-semibold text-slate-950">Каталог пока пуст</h3>
                <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-slate-600">
                    После наполнения базы здесь появятся категории, секции, карточки товаров и персональные цены.
                </p>
            </div>
        @else
            <div class="catalog-infinite-feed mt-5" data-infinite-feed data-next-page="{{ $products->nextPageUrl() ?? '' }}">
                <div class="catalog-grid" data-infinite-grid>
                    @include('catalog.partials.product-feed-items', [
                        'products' => $products,
                        'animationStep' => 70,
                        'animationWindow' => 6,
                    ])
                </div>

                @include('catalog.partials.infinite-feed-trigger', [
                    'nextPageUrl' => $products->nextPageUrl(),
                    'loadingLabel' => 'Подгружаем еще позиции каталога',
                    'fallbackLabel' => 'Показать еще товары',
                ])
            </div>
        @endif
    </section>
@endsection
