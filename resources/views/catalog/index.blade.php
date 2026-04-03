@extends('layouts.app')

@section('title', 'Каталог MAJOR')

@section('content')
    @php($profile = auth()->user()->priceProfile)
    @php($categoryAccents = ['#d11117', '#c81e1e', '#b91c1c', '#991b1b', '#be123c', '#7f1d1d'])
    @php($primaryBannerCategory = $rootCategories->first())
    @php($secondaryBannerCategory = $rootCategories->skip(1)->first() ?? $primaryBannerCategory)
    @php($tertiaryBannerCategory = $rootCategories->skip(2)->first() ?? $secondaryBannerCategory)
    @php($bannerSlides = [
        [
            'eyebrow' => 'Логистика MAJOR',
            'title' => 'Доставка по Удмуртии и в ближайшие города России —',
            'accent' => 'БЕСПЛАТНО',
            'description' => 'Закрытый B2B-кабинет помогает быстро подтверждать заявку, видеть свою цену и работать с актуальным каталогом без лишних переписок по каждому артикулу.',
            'action_label' => 'Открыть каталог',
            'action_url' => route('catalog.index'),
            'secondary_label' => 'Смотреть профили',
            'secondary_url' => $primaryBannerCategory ? route('categories.show', $primaryBannerCategory) : route('catalog.index'),
            'figure' => '24/7',
            'figure_caption' => 'поддержка менеджера',
            'panel_label' => 'Отгрузка',
            'panel_text' => 'По согласованному графику для салонов, монтажных команд и дилеров.',
            'points' => ['Без доплаты по Удмуртии', 'Ближайшие города России', 'Подтверждение в рабочем кабинете'],
            'tone' => 'delivery',
        ],
        [
            'eyebrow' => 'Персональные условия',
            'title' => 'Для каждого клиента',
            'accent' => 'СВОЙ ПРАЙС',
            'description' => 'Менеджер, партнёр и VIP-клиент видят только свой ценовой профиль. Сайт уже готов к автоматическому обновлению цен из 1С.',
            'action_label' => 'Проверить условия',
            'action_url' => route('catalog.index'),
            'secondary_label' => 'Каталог расходки',
            'secondary_url' => $secondaryBannerCategory ? route('categories.show', $secondaryBannerCategory) : route('catalog.index'),
            'figure' => $profile?->price_label ?? 'Цена 1',
            'figure_caption' => 'активная колонка',
            'panel_label' => 'Профиль',
            'panel_text' => 'В кабинете отображается только ваш прайс и связанная история заказов.',
            'points' => ['Гибкая модель доступа', 'Цены из 1С', 'Оформление заказа онлайн'],
            'tone' => 'pricing',
        ],
        [
            'eyebrow' => 'Единая витрина',
            'title' => 'Профили, карнизы и инструмент',
            'accent' => 'В ОДНОМ КАБИНЕТЕ',
            'description' => 'Быстрый поиск по линейке, избранное, корзина, история продаж и подбор похожих товаров внутри карточек без переходов между системами.',
            'action_label' => 'Перейти к товарам',
            'action_url' => route('catalog.index'),
            'secondary_label' => 'Категории каталога',
            'secondary_url' => $tertiaryBannerCategory ? route('categories.show', $tertiaryBannerCategory) : route('catalog.index'),
            'figure' => number_format($totalProducts, 0, ',', ' '),
            'figure_caption' => 'товаров в базе',
            'panel_label' => 'Каталог',
            'panel_text' => 'Навигация по категориям, разделам и свежим позициям на одном экране.',
            'points' => ["{$rootCategories->count()} категорий", "{$totalSections} разделов", "{$totalProducts} товаров"],
            'tone' => 'catalog',
        ],
    ])

    <section class="catalog-home-grid catalog-home-grid--solo">
        <div class="surface-card reveal-card catalog-home-banners" data-home-banners data-home-banner-interval="6800">
            <div class="catalog-home-banners__viewport">
                @foreach ($bannerSlides as $slide)
                    <article
                        class="catalog-home-banner catalog-home-banner--{{ $slide['tone'] }} {{ $loop->first ? 'is-active' : '' }}"
                        data-home-banner-slide
                        aria-hidden="{{ $loop->first ? 'false' : 'true' }}"
                    >
                        <div class="catalog-home-banner__copy">
                            <span class="soft-badge catalog-home-banner__badge">{{ $slide['eyebrow'] }}</span>
                            <h1 class="catalog-home-banner__title">
                                {{ $slide['title'] }}
                                <span>{{ $slide['accent'] }}</span>
                            </h1>
                            <p class="catalog-home-banner__description">{{ $slide['description'] }}</p>

                            <div class="catalog-home-banner__actions">
                                <a href="{{ $slide['action_url'] }}" class="action-button">{{ $slide['action_label'] }}</a>
                                <a href="{{ $slide['secondary_url'] }}" class="catalog-home-banner__ghost">
                                    {{ $slide['secondary_label'] }}
                                </a>
                            </div>
                        </div>

                        <div class="catalog-home-banner__visual" aria-hidden="true">
                            <div class="catalog-home-banner__figure">
                                <span>{{ $slide['panel_label'] }}</span>
                                <strong>{{ $slide['figure'] }}</strong>
                                <small>{{ $slide['figure_caption'] }}</small>
                            </div>

                            <div class="catalog-home-banner__panel">
                                <span>{{ $slide['panel_label'] }}</span>
                                <p>{{ $slide['panel_text'] }}</p>
                            </div>

                            <div class="catalog-home-banner__list">
                                @foreach ($slide['points'] as $point)
                                    <span>{{ $point }}</span>
                                @endforeach
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="catalog-home-banners__footer">
                <div class="catalog-home-banners__progress">
                    <span data-home-banner-progress></span>
                </div>

                <div class="catalog-home-banners__meta catalog-home-banners__meta--compact">
                    <div class="catalog-home-banners__controls">
                        <button type="button" class="catalog-home-banners__arrow" data-home-banner-prev aria-label="Предыдущий баннер">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M14.5 5 8 12l6.5 7" />
                            </svg>
                        </button>

                        <div class="catalog-home-banners__dots" role="tablist" aria-label="Навигация по баннерам">
                            @foreach ($bannerSlides as $index => $slide)
                                <button
                                    type="button"
                                    class="catalog-home-banners__dot {{ $loop->first ? 'is-active' : '' }}"
                                    data-home-banner-dot="{{ $index }}"
                                    aria-label="Баннер {{ $index + 1 }}"
                                    aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                                ></button>
                            @endforeach
                        </div>

                        <button type="button" class="catalog-home-banners__arrow" data-home-banner-next aria-label="Следующий баннер">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m9.5 5 6.5 7-6.5 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if ($rootCategories->isNotEmpty())
        <section class="mt-10">
            <div class="catalog-section-head">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Навигация</p>
                    <h2 class="mt-2 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Ключевые категории</h2>
                </div>
                <p class="max-w-xl text-sm leading-6 text-slate-600">
                    Категории организованы по листам прайса и раскладываются по секциям, чтобы каталог был похож на плотную B2B-витрину.
                </p>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($rootCategories as $index => $category)
                    <a
                        href="{{ route('categories.show', $category) }}"
                        class="category-tile reveal-card"
                        style="animation-delay: {{ $index * 70 }}ms; --card-accent: {{ $categoryAccents[$index % count($categoryAccents)] }};"
                    >
                        <span class="soft-badge bg-white/20 text-white">{{ $category->children->count() }} секц.</span>
                        <h3 class="mt-12 max-w-[12rem] font-['IBM_Plex_Sans'] text-2xl font-semibold leading-tight text-white">{{ $category->name }}</h3>
                        <p class="mt-4 text-sm text-white/85">{{ $category->catalog_products_count }} товаров в этом направлении</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($featuredProducts->isNotEmpty())
        <section class="mt-10">
            <div class="catalog-section-head">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Срез каталога</p>
                    <h2 class="mt-2 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Свежие позиции</h2>
                </div>
                <p class="max-w-xl text-sm leading-6 text-slate-600">
                    Последние добавленные позиции каталога. Их удобно использовать как быстрый обзор актуального наполнения.
                </p>
            </div>

            <div class="catalog-grid mt-5">
                @foreach ($featuredProducts->take(4) as $index => $product)
                    @include('catalog.partials.product-card', ['product' => $product, 'delay' => $index * 90])
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-10">
        <div class="catalog-section-head">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Товары</p>
                <h2 class="mt-2 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Каталог товаров</h2>
            </div>

            <p class="max-w-xl text-sm leading-6 text-slate-600">
                @if (request('q'))
                    Результаты по запросу «{{ request('q') }}».
                @elseif ($selectedCategory)
                    Показаны товары из категории «{{ $selectedCategory->name }}».
                @else
                    Полная выборка товаров для текущего профиля доступа.
                @endif
            </p>
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
