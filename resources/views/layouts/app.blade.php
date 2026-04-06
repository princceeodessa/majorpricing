<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', 'MAJOR Catalog')</title>

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
                <header class="catalog-container catalog-site-header py-4">
                    <div class="catalog-simple-header surface-card">
                        <div class="catalog-simple-header__primary">
                            <a href="{{ route('catalog.index') }}" class="catalog-header-logo" aria-label="MAJOR">
                                <span>MAJOR</span>
                            </a>

                            <nav class="catalog-simple-header__nav" aria-label="Основная навигация">
                                <a
                                    href="{{ route('catalog.index') }}"
                                    class="catalog-simple-header__link {{ request()->routeIs('catalog.index', 'categories.*', 'products.*') ? 'is-active' : '' }}"
                                >
                                    Каталог
                                </a>
                                <a
                                    href="{{ route('account.show') }}"
                                    class="catalog-simple-header__link {{ request()->routeIs('account.show', 'manager.users.*') ? 'is-active' : '' }}"
                                >
                                    Кабинет
                                </a>
                                <a
                                    href="{{ route('favorites.index') }}"
                                    class="catalog-simple-header__link {{ request()->routeIs('favorites.*') ? 'is-active' : '' }}"
                                >
                                    Избранное
                                    <span>{{ $headerFavoritesCount ?? 0 }}</span>
                                </a>
                                <a
                                    href="{{ route('cart.index') }}"
                                    class="catalog-simple-header__link {{ request()->routeIs('cart.*') ? 'is-active' : '' }}"
                                >
                                    Корзина
                                    <span>{{ $headerCartCount ?? 0 }}</span>
                                </a>
                                <a
                                    href="{{ route('orders.index') }}"
                                    class="catalog-simple-header__link {{ request()->routeIs('orders.*') ? 'is-active' : '' }}"
                                >
                                    Заказы
                                    <span>{{ $headerOrdersCount ?? 0 }}</span>
                                </a>
                            </nav>
                        </div>

                        <div class="catalog-simple-header__meta">
                            <span class="soft-badge catalog-simple-header__badge">
                                {{ auth()->user()->isManager() ? 'Менеджер' : 'Пользователь' }}
                            </span>

                            <div class="catalog-header-meta-pill">
                                <span>Логин</span>
                                <strong>{{ auth()->user()->login }}</strong>
                            </div>

                            <div class="catalog-header-meta-pill">
                                <span>Профиль</span>
                                <strong>{{ auth()->user()->priceProfile?->name ?? 'Не назначен' }}</strong>
                            </div>

                            <div class="catalog-header-meta-pill">
                                <span>Компания</span>
                                <strong>{{ auth()->user()->company ?? auth()->user()->name }}</strong>
                            </div>

                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="catalog-header-exit">Выйти</button>
                            </form>
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
        </div>
    </body>
</html>
