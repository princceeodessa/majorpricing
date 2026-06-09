@extends('layouts.app')

@section('title', 'Каталог МАЖОР')

@section('content')
    {{-- Карусель брендов: только на главной каталога, без выбранной категории и без поиска --}}
    @if (! ($hasSearch ?? false) && empty($selectedCategory))
        @include('partials.brands-carousel', [
            'brands' => $brands ?? collect(),
            'selectedBrand' => $selectedBrand ?? null,
        ])
    @endif

    <section class="catalog-v2-feed-section">
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

            @unless ($hasSearch ?? false)
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
            @endunless
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
