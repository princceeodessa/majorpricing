@extends('layouts.app')

@section('title', 'Каталог ПОТОЛКОВЫЧ')

@section('content')
    @if (!($hasSearch ?? false) && $rootCategories->isNotEmpty())
        @php
            $categoryShowcaseItems = $rootCategories
                ->map(function ($category): array {
                    $previewImage = $category->catalog_preview_image
                        ? asset($category->catalog_preview_image)
                        : null;

                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                        'url' => route('categories.show', $category),
                        'accentColor' => $category->accent_color ?? '#163459',
                        'childrenCount' => $category->children->count(),
                        'productsCount' => (int) $category->catalog_products_count,
                        'previewImage' => $previewImage,
                        'previewTitle' => $category->catalog_preview_title ?? $category->name,
                        'mark' => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($category->name, 0, 2)),
                        'sections' => $category->children
                            ->take(5)
                            ->map(fn ($child): array => [
                                'name' => $child->name,
                                'url' => route('categories.show', $child),
                            ])
                            ->values()
                            ->all(),
                    ];
                })
                ->values();
            $categoryShowcaseProps = [
                'title' => 'Основные категории',
                'subtitle' => 'Переключайтесь между направлениями и сразу переходите в нужный раздел.',
                'summary' => sprintf(
                    '%d категорий · %d товаров',
                    $rootCategories->count(),
                    (int) $rootCategories->sum(fn ($category) => (int) $category->catalog_products_count),
                ),
                'items' => $categoryShowcaseItems->all(),
            ];
        @endphp

        <section>
            <div
                class="catalog-category-v3"
                data-vue-category-showcase
                data-vue-category-showcase-props='@json($categoryShowcaseProps, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)'
            >
                <div class="catalog-section-head catalog-section-head--clean">
                    <h2 class="catalog-section-title">Основные категории</h2>
                    <p class="catalog-category-v3__subtitle">
                        Переключайтесь между направлениями и сразу переходите в нужный раздел.
                    </p>
                    <p class="catalog-category-v3__summary">
                        {{ $rootCategories->count() }} категорий · {{ (int) $rootCategories->sum(fn ($category) => (int) $category->catalog_products_count) }} товаров
                    </p>
                </div>

                <div class="catalog-category-v3__tabs" role="tablist" aria-label="Категории каталога">
                    @foreach ($rootCategories as $category)
                        <a
                            href="{{ route('categories.show', $category) }}"
                            class="catalog-category-v3__tab"
                            data-category-tab
                        >
                            <span class="catalog-category-v3__tab-mark">
                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($category->name, 0, 2)) }}
                            </span>
                            <span class="catalog-category-v3__tab-title">{{ $category->name }}</span>
                            <span class="catalog-category-v3__tab-count">{{ $category->catalog_products_count }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="catalog-category-showcase mt-4" data-category-rail>
                    <button type="button" class="catalog-category-showcase__arrow is-left" data-category-rail-prev aria-label="Прокрутить категории влево">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M14.5 5 8 12l6.5 7" />
                        </svg>
                    </button>

                    <div class="catalog-category-showcase__viewport" data-category-rail-track>
                        <div class="catalog-category-showcase__track">
                            @foreach ($rootCategories as $index => $category)
                                @php
                                    $previewImage = $category->catalog_preview_image
                                        ? asset($category->catalog_preview_image)
                                        : null;
                                    $sectionPreview = $category->children->take(4);
                                @endphp

                                <a
                                    href="{{ route('categories.show', $category) }}"
                                    class="catalog-category-card reveal-card"
                                    style="animation-delay: {{ $index * 70 }}ms; --category-accent: {{ $category->accent_color ?? '#163459' }};"
                                >
                                    <div class="catalog-category-card__copy">
                                        <div class="catalog-category-card__head">
                                            <span class="catalog-category-card__badge">{{ $category->children->count() }} секц.</span>
                                        </div>

                                        <h3 class="catalog-category-card__title">{{ $category->name }}</h3>
                                        <p class="catalog-category-card__meta">{{ $category->catalog_products_count }} товаров в наличии</p>

                                        @if ($sectionPreview->isNotEmpty())
                                            <div class="catalog-category-card__sections">
                                                @foreach ($sectionPreview as $child)
                                                    <span class="catalog-category-card__section">{{ $child->name }}</span>
                                                @endforeach
                                            </div>
                                        @endif
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
            </div>
        </section>
    @endif

    <section class="mt-10 catalog-v2-feed-section">
        <div class="catalog-section-head catalog-section-head--clean catalog-section-head--with-filters">
            <div>
                <h2 class="catalog-section-title">{{ ($hasSearch ?? false) ? 'Результаты поиска' : 'Каталог товаров' }}</h2>
                @if ($hasSearch ?? false)
                    @php
                        $resultsCount = $products->total();
                        $resultsLabel = match (true) {
                            $resultsCount % 10 === 1 && $resultsCount % 100 !== 11 => 'товар',
                            in_array($resultsCount % 10, [2, 3, 4], true) && !in_array($resultsCount % 100, [12, 13, 14], true) => 'товара',
                            default => 'товаров',
                        };
                    @endphp
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        По запросу «{{ $searchQuery }}» найдено {{ $resultsCount }} {{ $resultsLabel }}.
                    </p>
                @endif
            </div>

            @php
                $feedLinks = [
                    'new' => 'Новинки',
                    'hit' => 'Хит',
                    'in_stock' => 'В наличии',
                ];
                $feedItems = collect($feedLinks)
                    ->map(fn (string $feedLabel, string $feedKey): array => [
                        'key' => $feedKey,
                        'label' => $feedLabel,
                        'url' => route('catalog.index', array_merge(request()->except('page', 'feed'), ['feed' => $feedKey])),
                    ])
                    ->values();
                $feedSwitcherProps = [
                    'initialFeed' => $feed ?? 'new',
                    'items' => $feedItems->all(),
                ];
            @endphp

            <div
                class="catalog-feed-filters"
                data-vue-feed-switcher
                data-vue-feed-props='@json($feedSwitcherProps, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)'
                aria-label="Сортировка каталога"
            >
                @foreach ($feedLinks as $feedKey => $feedLabel)
                    <a
                        href="{{ route('catalog.index', array_merge(request()->except('page', 'feed'), ['feed' => $feedKey])) }}"
                        class="catalog-feed-filter {{ ($feed ?? 'new') === $feedKey ? 'is-active' : '' }}"
                    >
                        {{ $feedLabel }}
                    </a>
                @endforeach
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
