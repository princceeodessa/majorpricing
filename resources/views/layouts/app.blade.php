<!DOCTYPE html>
<html lang="ru">
    <head>
        @php
            $metaTitle = trim($__env->yieldContent('title', 'ПОТОЛКОВЫЧ'));
            $metaDescription = trim($__env->yieldContent('meta_description', 'Каталог ПОТОЛКОВЫЧ с актуальным ассортиментом, единой ценой и быстрым оформлением заказов для клиентов.'));
            $metaImage = trim($__env->yieldContent('meta_image', asset('brand/potolkovych-emblem.png')));
            $metaUrl = url()->current();
        @endphp

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $metaTitle }}</title>
        <meta name="description" content="{{ $metaDescription }}">
        <meta name="theme-color" content="#163459">

        <link rel="canonical" href="{{ $metaUrl }}">
        <link rel="icon" type="image/png" sizes="500x506" href="{{ asset('brand/potolkovych-emblem.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('brand/potolkovych-emblem.png') }}">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">

        <meta property="og:type" content="website">
        <meta property="og:locale" content="ru_RU">
        <meta property="og:site_name" content="ПОТОЛКОВЫЧ">
        <meta property="og:title" content="{{ $metaTitle }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta property="og:url" content="{{ $metaUrl }}">
        <meta property="og:image" content="{{ $metaImage }}">
        <meta property="og:image:alt" content="ПОТОЛКОВЫЧ">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $metaTitle }}">
        <meta name="twitter:description" content="{{ $metaDescription }}">
        <meta name="twitter:image" content="{{ $metaImage }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600,700|manrope:400,500,600,700,800" rel="stylesheet" />

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="catalog-body">
        <div class="catalog-backdrop"></div>

        <div class="catalog-shell">
            @if (auth()->check())
                @php($routeCategory = request()->route('category'))

                <header class="catalog-container catalog-site-header py-4">
                    <div class="catalog-header-shell catalog-header-shell--figma">
                        <div class="catalog-header-top">
                            <div class="catalog-header-brand">
                                <a href="{{ route('catalog.index') }}" class="catalog-header-logo" aria-label="ПОТОЛКОВЫЧ">
                                    <img src="{{ asset('brand/potolkovych-logo-wide.svg') }}" alt="ПОТОЛКОВЫЧ" class="catalog-header-logo__image">
                                </a>

                                <details class="catalog-header-catalog-menu">
                                    <summary class="catalog-header-catalog-trigger {{ request()->routeIs('catalog.*', 'categories.*', 'products.*') ? 'is-active' : '' }}">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <rect x="4" y="4" width="6" height="6" rx="1.5" />
                                            <rect x="14" y="4" width="6" height="6" rx="1.5" />
                                            <rect x="4" y="14" width="6" height="6" rx="1.5" />
                                            <rect x="14" y="14" width="6" height="6" rx="1.5" />
                                        </svg>
                                        <span>Каталог</span>
                                    </summary>

                                    <div class="catalog-header-catalog-dropdown">
                                        <a href="{{ route('catalog.index') }}" class="catalog-header-catalog-dropdown__all">
                                            Весь каталог
                                        </a>

                                        @if (($navCategories ?? collect())->isNotEmpty())
                                            <div class="catalog-header-catalog-dropdown__grid">
                                                @foreach ($navCategories as $navCategory)
                                                    @php($isActiveCategory = request()->routeIs('categories.show') && $routeCategory && ($routeCategory->id === $navCategory->id || $routeCategory->parent_id === $navCategory->id))
                                                    <a
                                                        href="{{ route('categories.show', $navCategory) }}"
                                                        class="catalog-header-catalog-dropdown__link {{ $isActiveCategory ? 'is-active' : '' }}"
                                                    >
                                                        {{ $navCategory->name }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="catalog-header-catalog-dropdown__empty">Категории появятся после наполнения каталога.</p>
                                        @endif
                                    </div>
                                </details>
                            </div>

                            <form action="{{ route('catalog.index') }}" method="GET" class="catalog-header-searchbar catalog-header-searchbar--figma">
                                <span class="catalog-header-search-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <circle cx="11" cy="11" r="6.5" />
                                        <path d="M16 16l4.5 4.5" />
                                    </svg>
                                </span>
                                <input
                                    type="text"
                                    name="q"
                                    value="{{ request('q') }}"
                                    placeholder="Поиск"
                                    class="catalog-header-search-field"
                                >
                                <button type="submit" class="catalog-header-search-submit" aria-label="Искать">Искать</button>
                            </form>

                            <div class="catalog-header-actions catalog-header-actions--figma">
                                <a href="{{ route('account.show') }}" class="catalog-header-icon-link {{ request()->routeIs('account.*') ? 'is-active' : '' }}">
                                    <span class="catalog-header-icon-link__icon">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <circle cx="12" cy="8" r="3.5" />
                                            <path d="M5.5 19.5c1.9-3.3 4.2-4.9 6.5-4.9s4.6 1.6 6.5 4.9" />
                                        </svg>
                                    </span>
                                    <span class="catalog-header-icon-link__label">Кабинет</span>
                                </a>

                                @if (auth()->user()?->canManageClients())
                                    <a href="{{ route('manager.chats.index') }}" class="catalog-header-icon-link {{ request()->routeIs('manager.chats.*') ? 'is-active' : '' }}">
                                        <span class="catalog-header-icon-link__icon">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M5 6.5A3.5 3.5 0 0 1 8.5 3h7A3.5 3.5 0 0 1 19 6.5v5A3.5 3.5 0 0 1 15.5 15H11l-4.8 4v-4.4A3.5 3.5 0 0 1 5 11.9Z" />
                                            </svg>
                                        </span>
                                        <span class="catalog-header-icon-link__label">Чаты</span>
                                    </a>
                                @endif

                                @if (auth()->user()?->isAdmin())
                                    <a href="{{ route('admin.onec.show') }}" class="catalog-header-icon-link {{ request()->routeIs('admin.onec.*') ? 'is-active' : '' }}">
                                        <span class="catalog-header-icon-link__icon">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M4.5 7.5h15M7.5 4.5v15M16.5 4.5v15M5.5 18.5h13" />
                                            </svg>
                                        </span>
                                        <span class="catalog-header-icon-link__label">1С</span>
                                    </a>
                                @endif

                                <a href="{{ route('favorites.index') }}" class="catalog-header-icon-link catalog-header-icon-link--mobile-hidden {{ request()->routeIs('favorites.*') ? 'is-active' : '' }}">
                                    <span class="catalog-header-icon-link__icon">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M12 20.2 4.9 13.4a4.5 4.5 0 0 1 6.4-6.3L12 7.8l.7-.7a4.5 4.5 0 1 1 6.4 6.3Z" />
                                        </svg>
                                        <strong class="catalog-header-icon-link__badge" data-favorites-count>{{ $headerFavoritesCount ?? 0 }}</strong>
                                    </span>
                                    <span class="catalog-header-icon-link__label">Избранное</span>
                                </a>

                                <a href="{{ route('orders.index') }}" class="catalog-header-icon-link catalog-header-icon-link--mobile-hidden {{ request()->routeIs('orders.*') ? 'is-active' : '' }}">
                                    <span class="catalog-header-icon-link__icon">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <rect x="5" y="4.5" width="14" height="15" rx="2.3" />
                                            <path d="M8 9.5h8M8 13h8M8 16.5h5" />
                                        </svg>
                                        <strong class="catalog-header-icon-link__badge">{{ $headerOrdersCount ?? 0 }}</strong>
                                    </span>
                                    <span class="catalog-header-icon-link__label">Заказы</span>
                                </a>

                                <a href="{{ route('cart.index') }}" class="catalog-header-icon-link catalog-header-icon-link--mobile-hidden {{ request()->routeIs('cart.*') ? 'is-active' : '' }}">
                                    <span class="catalog-header-icon-link__icon">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M4 7h15l-1.3 8.2a2 2 0 0 1-2 1.7H8.4a2 2 0 0 1-2-1.6L4.8 5.5H2.5" />
                                            <circle cx="9.2" cy="20" r="1.3" />
                                            <circle cx="16.4" cy="20" r="1.3" />
                                        </svg>
                                        <strong class="catalog-header-icon-link__badge" data-cart-count>{{ $headerCartCount ?? 0 }}</strong>
                                    </span>
                                    <span class="catalog-header-icon-link__label">Корзина</span>
                                </a>

                                <form action="{{ route('logout') }}" method="POST" class="catalog-header-actions__logout">
                                    @csrf
                                    <button type="submit" class="catalog-header-exit">Выйти</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </header>
            @endif

            <main class="catalog-container catalog-main pb-16">
                @if (session('status'))
                    <div class="catalog-flash mb-4">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="catalog-flash catalog-flash--error mb-4">
                        {{ $errors->first() }}
                    </div>
                @endif

                @yield('content')
            </main>

            @if (auth()->check())
                <nav class="catalog-mobile-nav" aria-label="Основная навигация">
                    <a href="{{ route('catalog.index') }}" class="catalog-mobile-nav__link {{ request()->routeIs('catalog.index', 'categories.*', 'products.*') ? 'is-active' : '' }}">
                        <span class="catalog-mobile-nav__icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 10.5 12 4l8 6.5v8.2a1.3 1.3 0 0 1-1.3 1.3h-3.9v-6.1H9.2V20H5.3A1.3 1.3 0 0 1 4 18.7Z" />
                            </svg>
                        </span>
                        <span class="catalog-mobile-nav__label">Главная</span>
                    </a>

                    <a href="{{ route('favorites.index') }}" class="catalog-mobile-nav__link {{ request()->routeIs('favorites.*') ? 'is-active' : '' }}">
                        <span class="catalog-mobile-nav__icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 20.2 4.9 13.4a4.5 4.5 0 0 1 6.4-6.3L12 7.8l.7-.7a4.5 4.5 0 1 1 6.4 6.3Z" />
                            </svg>
                            <strong class="catalog-mobile-nav__badge" data-favorites-count>{{ $headerFavoritesCount ?? 0 }}</strong>
                        </span>
                        <span class="catalog-mobile-nav__label">Избранное</span>
                    </a>

                    <a href="{{ route('orders.index') }}" class="catalog-mobile-nav__link {{ request()->routeIs('orders.*') ? 'is-active' : '' }}">
                        <span class="catalog-mobile-nav__icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="5" y="4.5" width="14" height="15" rx="2.3" />
                                <path d="M8 9.5h8M8 13h8M8 16.5h5" />
                            </svg>
                            <strong class="catalog-mobile-nav__badge">{{ $headerOrdersCount ?? 0 }}</strong>
                        </span>
                        <span class="catalog-mobile-nav__label">Заказы</span>
                    </a>

                    <a href="{{ route('cart.index') }}" class="catalog-mobile-nav__link {{ request()->routeIs('cart.*') ? 'is-active' : '' }}">
                        <span class="catalog-mobile-nav__icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 7h15l-1.3 8.2a2 2 0 0 1-2 1.7H8.4a2 2 0 0 1-2-1.6L4.8 5.5H2.5" />
                                <circle cx="9.2" cy="20" r="1.3" />
                                <circle cx="16.4" cy="20" r="1.3" />
                            </svg>
                            <strong class="catalog-mobile-nav__badge" data-cart-count>{{ $headerCartCount ?? 0 }}</strong>
                        </span>
                        <span class="catalog-mobile-nav__label">Корзина</span>
                    </a>

                    <a href="{{ route('account.show') }}" class="catalog-mobile-nav__link {{ request()->routeIs('account.*') ? 'is-active' : '' }}">
                        <span class="catalog-mobile-nav__icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="8" r="3.5" />
                                <path d="M5.5 19.5c1.9-3.3 4.2-4.9 6.5-4.9s4.6 1.6 6.5 4.9" />
                            </svg>
                        </span>
                        <span class="catalog-mobile-nav__label">Кабинет</span>
                    </a>
                </nav>
            @endif
        </div>

        @include('partials.support-widget')
    </body>
</html>
