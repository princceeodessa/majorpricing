@extends('layouts.app')

@section('title', 'Каталог MAJOR')

@section('content')
    @if (!($hasSearch ?? false) && $rootCategories->isNotEmpty())
        <section>
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

    @if (!($hasSearch ?? false) && $featuredProducts->isNotEmpty())
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
            <div>
                <h2 class="catalog-section-title">{{ ($hasSearch ?? false) ? 'Результаты поиска' : 'Каталог товаров' }}</h2>
                @if ($hasSearch ?? false)
                    @php($resultsCount = $products->total())
                    @php($resultsLabel = match (true) {
                        $resultsCount % 10 === 1 && $resultsCount % 100 !== 11 => 'товар',
                        in_array($resultsCount % 10, [2, 3, 4], true) && !in_array($resultsCount % 100, [12, 13, 14], true) => 'товара',
                        default => 'товаров',
                    })
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        По запросу «{{ $searchQuery }}» найдено {{ $resultsCount }} {{ $resultsLabel }}.
                    </p>
                @endif
            </div>
        </div>

        @if ($products->isEmpty())
            <div class="surface-card mt-5 p-10 text-center">
                @if ($hasSearch ?? false)
                    <h3 class="font-['IBM_Plex_Sans'] text-2xl font-semibold text-slate-950">Ничего не найдено</h3>
                    <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-slate-600">
                        Попробуйте изменить запрос или использовать более короткое название товара.
                    </p>
                @else
                    <h3 class="font-['IBM_Plex_Sans'] text-2xl font-semibold text-slate-950">Каталог пока пуст</h3>
                    <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-slate-600">
                        После наполнения базы здесь появятся категории, секции и карточки товаров с единой ценой.
                    </p>
                @endif
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
