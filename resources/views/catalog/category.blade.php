@extends('layouts.app')

@section('title', $category->name.' - ПОТОЛКОВЫЧ')

@section('content')
    @php($showPrices = $showPrices ?? auth()->check())
    @php($sectionTotal = $category->children->isNotEmpty() ? (int) $sectionCounts->sum() : $products->total())
    @php($activeFilters = collect([request('q'), $selectedSection?->id, $selectedSheet, $showPrices && $hasActivePriceFilter ? 'price' : null])->filter()->count())
    @php($rangeMin = $priceBounds['min'] !== null ? (float) floor($priceBounds['min']) : 0)
    @php($rangeMax = $priceBounds['max'] !== null ? (float) ceil($priceBounds['max']) : 0)
    @php($formPriceMin = $selectedPriceMin !== null ? number_format((float) $selectedPriceMin, 2, '.', '') : '')
    @php($formPriceMax = $selectedPriceMax !== null ? number_format((float) $selectedPriceMax, 2, '.', '') : '')

    <section class="surface-card reveal-card catalog-page-hero catalog-category-hero p-6 sm:p-8">
        <div class="catalog-list-header">
            <div class="max-w-4xl">
                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">
                    <a href="{{ route('catalog.index') }}" class="transition hover:text-slate-900">Каталог</a>
                    @if ($category->parent)
                        <span>/</span>
                        <a href="{{ route('categories.show', $category->parent) }}" class="transition hover:text-slate-900">{{ $category->parent->name }}</a>
                    @endif
                    <span>/</span>
                    <span class="text-slate-900">{{ $category->name }}</span>
                </div>

                <h1 class="mt-4 font-['IBM_Plex_Sans'] text-4xl font-semibold tracking-tight text-slate-950 sm:text-[3.2rem]">{{ $category->name }}</h1>
                <p class="mt-4 max-w-3xl text-base leading-7 text-slate-600">
                    @if ($showPrices)
                        Белая каталоговая раскладка с боковой фильтрацией по секциям, единой цене и серии. Все значения на странице показываются в общем режиме без профилей цен.
                    @else
                        Каталог открыт для просмотра. Цены и оформление заказа доступны после подтверждения партнерского доступа.
                    @endif
                </p>
            </div>

            <div class="catalog-list-header__stats">
                <div class="catalog-stat-box">
                    <span>Найдено</span>
                    <strong>{{ $products->total() }}</strong>
                </div>
                <div class="catalog-stat-box">
                    <span>Фильтров</span>
                    <strong>{{ $activeFilters }}</strong>
                </div>
            </div>
        </div>
    </section>

    <section class="catalog-browser mt-6">
        <aside class="surface-card reveal-card catalog-filter-card p-6 sm:p-7 lg:sticky lg:top-6">
            <form
                action="{{ route('categories.show', $category) }}"
                method="GET"
                class="catalog-filter-form space-y-8"
                @if ($priceBounds['min'] !== null && $priceBounds['max'] !== null)
                    data-price-filter
                    data-min-bound="{{ $rangeMin }}"
                    data-max-bound="{{ $rangeMax }}"
                @endif
            >
                <div class="catalog-filter-section">
                    <p class="catalog-filter-title">Поиск</p>
                    <input
                        type="text"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Поиск внутри раздела"
                        class="catalog-clean-input"
                    >
                </div>

                @if ($category->children->isNotEmpty())
                    <div class="catalog-filter-section">
                        <p class="catalog-filter-title">Раздел</p>
                        <div class="catalog-option-list">
                            <label class="catalog-option-card">
                                <input type="radio" name="section" value="" @checked($selectedSection === null)>
                                <span class="catalog-option-card__mark"></span>
                                <span class="catalog-option-card__label">Все</span>
                                <span class="catalog-option-card__count">{{ $sectionTotal }}</span>
                            </label>

                            @foreach ($category->children as $child)
                                <label class="catalog-option-card">
                                    <input type="radio" name="section" value="{{ $child->slug }}" @checked($selectedSection?->id === $child->id)>
                                    <span class="catalog-option-card__mark"></span>
                                    <span class="catalog-option-card__label">{{ $child->name }}</span>
                                    <span class="catalog-option-card__count">{{ $sectionCounts[$child->id] ?? 0 }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($priceBounds['min'] !== null && $priceBounds['max'] !== null)
                    <div class="catalog-filter-section">
                        <p class="catalog-filter-title">Цена</p>

                        <div class="catalog-price-filter">
                            <div class="catalog-price-filter__slider">
                                <div class="catalog-price-filter__track"></div>
                                <div class="catalog-price-filter__fill" data-range-fill></div>
                                <input
                                    type="range"
                                    min="{{ $rangeMin }}"
                                    max="{{ $rangeMax }}"
                                    step="1"
                                    value="{{ $selectedPriceMin !== null ? (float) floor($selectedPriceMin) : $rangeMin }}"
                                    class="catalog-price-filter__range"
                                    data-range-start
                                >
                                <input
                                    type="range"
                                    min="{{ $rangeMin }}"
                                    max="{{ $rangeMax }}"
                                    step="1"
                                    value="{{ $selectedPriceMax !== null ? (float) ceil($selectedPriceMax) : $rangeMax }}"
                                    class="catalog-price-filter__range"
                                    data-range-end
                                >
                            </div>

                            <div class="catalog-price-filter__inputs">
                                <input
                                    type="number"
                                    name="price_min"
                                    value="{{ $formPriceMin }}"
                                    min="{{ $rangeMin }}"
                                    max="{{ $rangeMax }}"
                                    step="0.01"
                                    class="catalog-clean-input"
                                    data-range-start-display
                                >
                                <span>—</span>
                                <input
                                    type="number"
                                    name="price_max"
                                    value="{{ $formPriceMax }}"
                                    min="{{ $rangeMin }}"
                                    max="{{ $rangeMax }}"
                                    step="0.01"
                                    class="catalog-clean-input"
                                    data-range-end-display
                                >
                            </div>
                        </div>
                    </div>
                @endif

                @if ($availableSheets->isNotEmpty())
                    <div class="catalog-filter-section">
                        <p class="catalog-filter-title">Серия</p>
                        <div class="catalog-option-list">
                            <label class="catalog-option-card">
                                <input type="radio" name="sheet" value="" @checked($selectedSheet === '')>
                                <span class="catalog-option-card__mark"></span>
                                <span class="catalog-option-card__label">Все</span>
                            </label>

                            @foreach ($availableSheets as $sheet)
                                <label class="catalog-option-card">
                                    <input type="radio" name="sheet" value="{{ $sheet->source_sheet }}" @checked($selectedSheet === $sheet->source_sheet)>
                                    <span class="catalog-option-card__mark"></span>
                                    <span class="catalog-option-card__label">{{ $sheet->source_sheet }}</span>
                                    <span class="catalog-option-card__count">{{ $sheet->aggregate }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="catalog-filter-actions">
                    <button type="submit" class="catalog-apply-button">Показать</button>
                    <a href="{{ route('categories.show', $category) }}" class="catalog-reset-button">Сбросить</a>
                </div>
            </form>
        </aside>

        <div class="space-y-5">
            <div class="surface-card reveal-card catalog-results-panel p-6 sm:p-7">
                <div class="catalog-results-bar">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Выдача</p>
                        <h2 class="mt-2 font-['IBM_Plex_Sans'] text-3xl font-semibold tracking-tight text-slate-950">Товары раздела</h2>
                    </div>

                    <div class="catalog-results-bar__meta">
                        @if (request('q'))
                            <span class="catalog-inline-chip">Поиск: {{ request('q') }}</span>
                        @endif

                        @if ($selectedSection)
                            <span class="catalog-inline-chip">Раздел: {{ $selectedSection->name }}</span>
                        @endif

                        @if ($selectedSheet !== '')
                            <span class="catalog-inline-chip">Серия: {{ $selectedSheet }}</span>
                        @endif

                        @if ($hasActivePriceFilter)
                            <span class="catalog-inline-chip">Цена: {{ number_format((float) $selectedPriceMin, 0, ',', ' ') }} - {{ number_format((float) $selectedPriceMax, 0, ',', ' ') }}</span>
                        @endif
                    </div>
                </div>
            </div>

            @if ($products->isEmpty())
                <div class="surface-card p-12 text-center">
                    <h2 class="font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Ничего не найдено</h2>
                    <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-slate-600">
                        Попробуйте расширить диапазон цены, сбросить серию или изменить поисковый запрос.
                    </p>
                </div>
            @else
                <div class="catalog-infinite-feed" data-infinite-feed data-next-page="{{ $products->nextPageUrl() ?? '' }}">
                    <div class="catalog-grid catalog-grid--dense" data-infinite-grid>
                        @include('catalog.partials.product-feed-items', [
                            'products' => $products,
                            'animationStep' => 55,
                            'animationWindow' => 8,
                        ])
                    </div>

                    @include('catalog.partials.infinite-feed-trigger', [
                        'nextPageUrl' => $products->nextPageUrl(),
                        'loadingLabel' => 'Подгружаем еще товары раздела',
                        'fallbackLabel' => 'Показать еще товары',
                    ])
                </div>
            @endif
        </div>
    </section>
@endsection
