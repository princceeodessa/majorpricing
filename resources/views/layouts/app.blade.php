<!DOCTYPE html>
<html lang="ru">
    <head>
        @php
            $metaTitle = trim($__env->yieldContent('title', 'МАЖОР'));
            $metaDescription = trim($__env->yieldContent('meta_description', 'Каталог МАЖОР с актуальным ассортиментом, единой ценой и быстрым оформлением заказов для клиентов.'));
            $metaImage = trim($__env->yieldContent('meta_image', asset('brand/major-favicon.png')));
            $metaUrl = url()->current();
        @endphp

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $metaTitle }}</title>
        <meta name="description" content="{{ $metaDescription }}">
        <meta name="theme-color" content="#d60000">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @auth
            <meta name="notifications-poll" content="{{ route('notifications.poll') }}">
            <meta name="notifications-user" content="{{ auth()->id() }}">
            <meta name="web-push-public-key" content="{{ route('web-push.public-key') }}">
            <meta name="web-push-subscribe" content="{{ route('web-push.subscriptions.store') }}">
            <meta name="web-push-unsubscribe" content="{{ route('web-push.subscriptions.destroy') }}">
        @endauth

        <link rel="canonical" href="{{ $metaUrl }}">
        <link rel="icon" type="image/png" href="{{ asset('brand/major-favicon.png') }}">
        <link rel="icon" type="image/svg+xml" href="{{ asset('brand/major-favicon.svg') }}">
        <link rel="apple-touch-icon" href="{{ asset('brand/major-favicon.png') }}">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">

        {{-- PWA: iOS standalone hints --}}
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="МАЖОР">

        <meta property="og:type" content="website">
        <meta property="og:locale" content="ru_RU">
        <meta property="og:site_name" content="МАЖОР">
        <meta property="og:title" content="{{ $metaTitle }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta property="og:url" content="{{ $metaUrl }}">
        <meta property="og:image" content="{{ $metaImage }}">
        <meta property="og:image:alt" content="МАЖОР">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $metaTitle }}">
        <meta name="twitter:description" content="{{ $metaDescription }}">
        <meta name="twitter:image" content="{{ $metaImage }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600,700|manrope:400,500,600,700,800" rel="stylesheet" />

        <script defer src="{{ asset('vendor/vue.global.prod.js') }}"></script>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

    </head>
    @php($isHome = request()->routeIs('catalog.index'))
    @php($isCatalogSurface = request()->routeIs('catalog.*', 'categories.*', 'products.*'))
    @php($showCatalogHeader = auth()->check() || $isCatalogSurface)
    <body class="catalog-body {{ $isHome ? 'is-home' : 'is-inner' }}">
        <a href="#catalog-main-content" class="catalog-skip-link">Перейти к содержимому</a>
        <div class="catalog-pageload-bar" id="catalog-pageload-bar" aria-hidden="true"></div>
        <div class="catalog-backdrop"></div>

        <div class="catalog-shell">
            @if ($showCatalogHeader)
                @php($routeCategory = request()->route('category'))

                <header class="catalog-container catalog-site-header py-4">
                    <div class="catalog-header-shell catalog-header-shell--figma">
                        <div class="catalog-header-top">
                            <div class="catalog-header-brand">
                                <a href="{{ route('catalog.index') }}" class="catalog-header-logo" aria-label="МАЖОР">
                                    <img src="{{ asset('brand/major-logo-wide.svg') }}" alt="МАЖОР" class="catalog-header-logo__image">
                                </a>

                                {{-- Mobile: direct link to /categories (Ozon-style grid page).
                                     Hidden on PC via media query in <style> below. --}}
                                <a
                                    href="{{ route('categories.index') }}"
                                    data-catalog-trigger="mobile"
                                    class="catalog-header-catalog-trigger {{ request()->routeIs('catalog.*', 'categories.*', 'products.*') ? 'is-active' : '' }}"
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <rect x="4" y="4" width="6" height="6" rx="1.5" />
                                        <rect x="14" y="4" width="6" height="6" rx="1.5" />
                                        <rect x="4" y="14" width="6" height="6" rx="1.5" />
                                        <rect x="14" y="14" width="6" height="6" rx="1.5" />
                                    </svg>
                                    <span>Каталог</span>
                                </a>

                                {{-- Desktop: dropdown with categories preview.
                                     Hidden on mobile via media query in <style> below. --}}
                                <details data-catalog-trigger="desktop" class="catalog-header-catalog-menu">
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
                                @auth
                                    @php($accountNotifications = (int) ($headerAccountNotificationsCount ?? 0))
                                    @php($managerUnreadMessages = (int) ($headerUnreadMessagesCount ?? 0))
                                    @php($ordersNotificationCount = (int) ($headerOrdersNotificationCount ?? 0))
                                    <a href="{{ route('account.show') }}" class="catalog-header-icon-link {{ request()->routeIs('account.*') ? 'is-active' : '' }}">
                                        <span class="catalog-header-icon-link__icon">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <circle cx="12" cy="8" r="3.5" />
                                                <path d="M5.5 19.5c1.9-3.3 4.2-4.9 6.5-4.9s4.6 1.6 6.5 4.9" />
                                            </svg>
                                            <strong class="catalog-header-icon-link__badge {{ $accountNotifications < 1 ? 'hidden' : '' }}" data-account-notifications-count data-notification-badge>{{ $accountNotifications }}</strong>
                                        </span>
                                        <span class="catalog-header-icon-link__label">Кабинет</span>
                                    </a>

                                    @if (auth()->user()?->canManageClients())
                                        <a href="{{ route('manager.chats.index') }}" class="catalog-header-icon-link {{ request()->routeIs('manager.chats.*') ? 'is-active' : '' }}">
                                            <span class="catalog-header-icon-link__icon">
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M5 6.5A3.5 3.5 0 0 1 8.5 3h7A3.5 3.5 0 0 1 19 6.5v5A3.5 3.5 0 0 1 15.5 15H11l-4.8 4v-4.4A3.5 3.5 0 0 1 5 11.9Z" />
                                                </svg>
                                                <strong class="catalog-header-icon-link__badge {{ $managerUnreadMessages < 1 ? 'hidden' : '' }}" data-manager-unread-messages-count data-notification-badge>{{ $managerUnreadMessages }}</strong>
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
                                            <strong class="catalog-header-icon-link__badge {{ $ordersNotificationCount < 1 ? 'hidden' : '' }}" data-order-notifications-count data-notification-badge>{{ $ordersNotificationCount }}</strong>
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
                                @else
                                    <a href="{{ route('login') }}" class="catalog-header-icon-link {{ request()->routeIs('login') ? 'is-active' : '' }}">
                                        <span class="catalog-header-icon-link__icon">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <circle cx="12" cy="8" r="3.5" />
                                                <path d="M5.5 19.5c1.9-3.3 4.2-4.9 6.5-4.9s4.6 1.6 6.5 4.9" />
                                            </svg>
                                        </span>
                                        <span class="catalog-header-icon-link__label">Войти</span>
                                    </a>

                                    <a href="{{ route('registration-requests.create') }}" class="catalog-header-icon-link">
                                        <span class="catalog-header-icon-link__icon">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <circle cx="9" cy="8" r="3.2" />
                                                <path d="M3.8 19c1.6-3.1 3.4-4.6 5.2-4.6 1.4 0 2.7.8 3.9 2.4" />
                                                <path d="M17.5 10.5v6M14.5 13.5h6" />
                                            </svg>
                                        </span>
                                        <span class="catalog-header-icon-link__label">Стать партнером</span>
                                    </a>
                                @endauth
                            </div>
                        </div>
                    </div>
                </header>

                {{-- Mobile-only compact search bar — replaces the desktop header on phones.
                     Show ONLY on catalog/categories/products screens. Hidden in personal cabinet,
                     cart, favorites and other non-catalog flows. --}}
                @if ($isCatalogSurface)
                    <div class="catalog-mobile-search-row">
                        <form action="{{ route('catalog.index') }}" method="GET" class="catalog-mobile-search" role="search">
                            <input
                                type="search"
                                name="q"
                                value="{{ request('q') }}"
                                placeholder="Поиск товара"
                                class="catalog-mobile-search__input"
                                aria-label="Поиск товара"
                            >
                            <button type="submit" class="catalog-mobile-search__submit" aria-label="Найти">
                                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                                    <circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/>
                                    <path d="M16.5 16.5L21 21" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </form>
                        <a
                            href="{{ route('categories.index') }}"
                            class="catalog-mobile-categories-button"
                            aria-label="Все категории"
                        >
                            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">
                                <rect x="3.5" y="3.5" width="6" height="6" rx="1.3" fill="none" stroke="currentColor" stroke-width="1.8"/>
                                <rect x="14.5" y="3.5" width="6" height="6" rx="1.3" fill="none" stroke="currentColor" stroke-width="1.8"/>
                                <rect x="3.5" y="14.5" width="6" height="6" rx="1.3" fill="none" stroke="currentColor" stroke-width="1.8"/>
                                <circle cx="17" cy="17" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M19.2 19.2L21 21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </a>
                    </div>
                @endif
            @else
                {{-- Auth / legal pages have no header — give the user a way back.
                     JS prefers history.back(); fallback to the catalog index. --}}
                <a
                    href="{{ url()->previous() && url()->previous() !== url()->current() ? url()->previous() : route('catalog.index') }}"
                    class="catalog-back-link"
                    data-back-link
                    aria-label="Назад"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>Назад</span>
                </a>
                <script>
                    (function () {
                        var link = document.querySelector('[data-back-link]');
                        if (! link) return;
                        link.addEventListener('click', function (e) {
                            if (window.history.length > 1) {
                                e.preventDefault();
                                window.history.back();
                            }
                            // else: follow the href (catalog index) as fallback
                        });
                    })();
                </script>
            @endif

            <main id="catalog-main-content" class="catalog-container catalog-main pb-16">
                @if ($errors->any())
                    <div class="catalog-toast catalog-toast--error" data-toast>
                        <div class="catalog-toast__content">
                            <strong>Ошибка</strong>
                            <p>{{ $errors->first() }}</p>
                        </div>
                        <button type="button" class="catalog-toast__close" data-toast-close aria-label="Закрыть">&times;</button>
                    </div>
                @endif

                @if (session('status'))
                    <div class="catalog-toast catalog-toast--success" data-toast>
                        <div class="catalog-toast__content">
                            <strong>Статус</strong>
                            <p>{{ session('status') }}</p>
                        </div>
                        <button type="button" class="catalog-toast__close" data-toast-close aria-label="Закрыть">&times;</button>
                    </div>
                @endif
                @yield('content')
            </main>

            @include('partials.footer')

            @if (auth()->check())
                <nav class="catalog-mobile-nav" aria-label="Основная навигация">
                    <a href="{{ route('catalog.index') }}" class="catalog-mobile-nav__link {{ request()->routeIs('catalog.index', 'catalog.shop', 'products.*', 'home') ? 'is-active' : '' }}">
                        <span class="catalog-mobile-nav__icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 10.5 12 4l8 6.5v8.2a1.3 1.3 0 0 1-1.3 1.3h-3.9v-6.1H9.2V20H5.3A1.3 1.3 0 0 1 4 18.7Z" />
                            </svg>
                        </span>
                        <span class="catalog-mobile-nav__label">Каталог</span>
                    </a>

                    <a href="{{ route('categories.index') }}" class="catalog-mobile-nav__link {{ request()->routeIs('categories.*') ? 'is-active' : '' }}">
                        <span class="catalog-mobile-nav__icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="4" y="4" width="6" height="6" rx="1.5" />
                                <rect x="14" y="4" width="6" height="6" rx="1.5" />
                                <rect x="4" y="14" width="6" height="6" rx="1.5" />
                                <rect x="14" y="14" width="6" height="6" rx="1.5" />
                            </svg>
                        </span>
                        <span class="catalog-mobile-nav__label">Категории</span>
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

                    <a href="{{ route('account.show') }}" class="catalog-mobile-nav__link {{ request()->routeIs('account.*', 'orders.*', 'manager.*') ? 'is-active' : '' }}">
                        <span class="catalog-mobile-nav__icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="8" r="3.5" />
                                <path d="M5.5 19.5c1.9-3.3 4.2-4.9 6.5-4.9s4.6 1.6 6.5 4.9" />
                            </svg>
                            <strong class="catalog-mobile-nav__badge {{ ((int) ($headerAccountNotificationsCount ?? 0)) < 1 ? 'hidden' : '' }}" data-account-notifications-count data-notification-badge>{{ (int) ($headerAccountNotificationsCount ?? 0) }}</strong>
                        </span>
                        <span class="catalog-mobile-nav__label">Кабинет</span>
                    </a>
                </nav>
            @elseif ($isCatalogSurface)
                <nav class="catalog-mobile-nav" aria-label="Основная навигация">
                    <a href="{{ route('catalog.index') }}" class="catalog-mobile-nav__link {{ request()->routeIs('catalog.index', 'catalog.shop', 'products.*', 'home') ? 'is-active' : '' }}">
                        <span class="catalog-mobile-nav__icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 10.5 12 4l8 6.5v8.2a1.3 1.3 0 0 1-1.3 1.3h-3.9v-6.1H9.2V20H5.3A1.3 1.3 0 0 1 4 18.7Z" />
                            </svg>
                        </span>
                        <span class="catalog-mobile-nav__label">Каталог</span>
                    </a>

                    <a href="{{ route('categories.index') }}" class="catalog-mobile-nav__link {{ request()->routeIs('categories.*') ? 'is-active' : '' }}">
                        <span class="catalog-mobile-nav__icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="4" y="4" width="6" height="6" rx="1.5" />
                                <rect x="14" y="4" width="6" height="6" rx="1.5" />
                                <rect x="4" y="14" width="6" height="6" rx="1.5" />
                                <rect x="14" y="14" width="6" height="6" rx="1.5" />
                            </svg>
                        </span>
                        <span class="catalog-mobile-nav__label">Категории</span>
                    </a>

                    <a href="{{ route('registration-requests.create') }}" class="catalog-mobile-nav__link {{ request()->routeIs('registration-requests.*') ? 'is-active' : '' }}">
                        <span class="catalog-mobile-nav__icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="9" cy="8" r="3.2" />
                                <path d="M3.8 19c1.6-3.1 3.4-4.6 5.2-4.6 1.4 0 2.7.8 3.9 2.4" />
                                <path d="M17.5 10.5v6M14.5 13.5h6" />
                            </svg>
                        </span>
                        <span class="catalog-mobile-nav__label">Партнер</span>
                    </a>

                    <a href="{{ route('login') }}" class="catalog-mobile-nav__link {{ request()->routeIs('login') ? 'is-active' : '' }}">
                        <span class="catalog-mobile-nav__icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="8" r="3.5" />
                                <path d="M5.5 19.5c1.9-3.3 4.2-4.9 6.5-4.9s4.6 1.6 6.5 4.9" />
                            </svg>
                        </span>
                        <span class="catalog-mobile-nav__label">Войти</span>
                    </a>
                </nav>
            @endif
        </div>

        @include('partials.sticky-cart-bar')
        @include('partials.support-widget')

        {{-- Brand-coloured page-loading progress bar --}}
        <script>
            (function () {
                var bar = document.getElementById('catalog-pageload-bar');
                if (!bar) return;
                var navigating = false;

                function start() {
                    if (navigating) return;
                    navigating = true;
                    bar.classList.remove('is-done');
                    bar.classList.add('is-active');
                }

                function finish() {
                    if (!navigating) return;
                    navigating = false;
                    bar.classList.remove('is-active');
                    bar.classList.add('is-done');
                    setTimeout(function () {
                        bar.classList.remove('is-done');
                    }, 400);
                }

                // Trigger on internal link clicks (same origin, not opening new tab, not anchor)
                document.addEventListener('click', function (e) {
                    var a = e.target.closest('a[href]');
                    if (!a) return;
                    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
                    if (a.target === '_blank' || a.hasAttribute('download')) return;
                    if (a.getAttribute('href').startsWith('#')) return;
                    try {
                        var url = new URL(a.href, location.href);
                        if (url.origin !== location.origin) return;
                        if (url.pathname === location.pathname && url.search === location.search) return;
                    } catch (_) { return; }
                    start();
                }, true);

                // Trigger on form submissions
                document.addEventListener('submit', function (e) {
                    var form = e.target;
                    if (!form || form.method === 'dialog') return;
                    start();
                }, true);

                // Hide when page is shown (works for bfcache too)
                window.addEventListener('pageshow', finish);
                window.addEventListener('load', finish);
            })();
        </script>

        {{-- Scroll-reveal: IntersectionObserver for opt-in elements --}}
        <script>
            (function () {
                if (!('IntersectionObserver' in window)) {
                    document.querySelectorAll('[data-scroll-reveal], .scroll-reveal')
                        .forEach(function (el) { el.classList.add('is-revealed'); });
                    return;
                }

                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-revealed');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

                function observeAll(root) {
                    (root || document).querySelectorAll('[data-scroll-reveal]:not(.is-revealed), .scroll-reveal:not(.is-revealed)')
                        .forEach(function (el) { observer.observe(el); });
                }

                observeAll();

                // Re-scan when new cards are added by infinite scroll
                var mo = new MutationObserver(function (mutations) {
                    mutations.forEach(function (m) {
                        m.addedNodes.forEach(function (node) {
                            if (node.nodeType === 1) observeAll(node);
                        });
                    });
                });
                mo.observe(document.body, { childList: true, subtree: true });
            })();
        </script>

        {{-- --catalog-vh, --catalog-mobile-nav-height, --catalog-header-height
             are written by setupResponsiveUiMetrics() in resources/js/app.js. --}}

        {{-- PWA: глобально регистрируем service worker и ловим beforeinstallprompt
             в window.__pwa.prompt, чтобы личный кабинет мог вызвать установку
             без плавающей плашки на каждой странице. --}}
        <script>
            (function () {
                if ('serviceWorker' in navigator && location.protocol === 'https:') {
                    window.addEventListener('load', function () {
                        navigator.serviceWorker.register('{{ asset('major-sw.js') }}', { scope: '/' })
                            .catch(function (error) {
                                if (window.console) console.warn('[pwa] sw registration failed', error);
                            });
                    });
                }

                window.__pwa = window.__pwa || { prompt: null, installed: false };

                window.addEventListener('beforeinstallprompt', function (event) {
                    event.preventDefault();
                    window.__pwa.prompt = event;
                    document.dispatchEvent(new CustomEvent('pwa:ready'));
                });

                window.addEventListener('appinstalled', function () {
                    window.__pwa.installed = true;
                    window.__pwa.prompt = null;
                    document.dispatchEvent(new CustomEvent('pwa:installed'));
                });
            })();
        </script>
    </body>
</html>
