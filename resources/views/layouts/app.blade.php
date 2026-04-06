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
                @php($routeCategory = request()->route('category'))

                <header class="catalog-container catalog-site-header py-4">
                    <div class="catalog-header-shell">
                        <div class="catalog-header-toolbar">
                            <a href="{{ route('catalog.index') }}" class="catalog-header-logo" aria-label="MAJOR">
                                <span>MAJOR</span>
                            </a>

                            <a href="{{ route('catalog.index') }}" class="catalog-header-catalog-trigger {{ request()->routeIs('catalog.*', 'categories.*', 'products.*') ? 'is-active' : '' }}">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M4 6.5h8M4 12h16M4 17.5h10" />
                                    <path d="M17 5l3 3-3 3" />
                                </svg>
                                <span>Каталог</span>
                            </a>

                            <form action="{{ route('catalog.index') }}" method="GET" class="catalog-header-searchbar">
                                <input
                                    type="text"
                                    name="q"
                                    value="{{ request('q') }}"
                                    placeholder="Искать в каталоге"
                                    class="catalog-header-search-field"
                                >
                                <button type="submit" class="catalog-header-search-submit" aria-label="Найти">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <circle cx="11" cy="11" r="6.5" />
                                        <path d="M16 16l4.5 4.5" />
                                    </svg>
                                </button>
                            </form>

                            <div class="catalog-header-actions">
                                <a href="{{ route('account.show') }}" class="catalog-header-icon-link {{ request()->routeIs('account.*') ? 'is-active' : '' }}">
                                    <span class="catalog-header-icon-link__icon">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <circle cx="12" cy="8" r="3.5" />
                                            <path d="M5.5 19.5c1.9-3.3 4.2-4.9 6.5-4.9s4.6 1.6 6.5 4.9" />
                                        </svg>
                                    </span>
                                    <span class="catalog-header-icon-link__label">Кабинет</span>
                                </a>

                                <a href="{{ route('favorites.index') }}" class="catalog-header-icon-link {{ request()->routeIs('favorites.*') ? 'is-active' : '' }}">
                                    <span class="catalog-header-icon-link__icon">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M12 20.2 4.9 13.4a4.5 4.5 0 0 1 6.4-6.3L12 7.8l.7-.7a4.5 4.5 0 1 1 6.4 6.3Z" />
                                        </svg>
                                        <strong class="catalog-header-icon-link__badge" data-favorites-count>{{ $headerFavoritesCount ?? 0 }}</strong>
                                    </span>
                                    <span class="catalog-header-icon-link__label">Избранное</span>
                                </a>

                                <a href="{{ route('cart.index') }}" class="catalog-header-icon-link {{ request()->routeIs('cart.*') ? 'is-active' : '' }}">
                                    <span class="catalog-header-icon-link__icon">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M4 7h15l-1.3 8.2a2 2 0 0 1-2 1.7H8.4a2 2 0 0 1-2-1.6L4.8 5.5H2.5" />
                                            <circle cx="9.2" cy="20" r="1.3" />
                                            <circle cx="16.4" cy="20" r="1.3" />
                                        </svg>
                                        <strong class="catalog-header-icon-link__badge">{{ $headerCartCount ?? 0 }}</strong>
                                    </span>
                                    <span class="catalog-header-icon-link__label">Корзина</span>
                                </a>

                                <form action="{{ route('logout') }}" method="POST" class="catalog-header-actions__logout">
                                    @csrf
                                    <button type="submit" class="catalog-header-exit">Выйти</button>
                                </form>
                            </div>
                        </div>

                        @if (($navCategories ?? collect())->isNotEmpty())
                            <div class="catalog-header-subline">
                                <div class="catalog-header-menu-wrap">
                                    <nav class="catalog-header-menu" aria-label="Навигация по категориям">
                                        @foreach ($navCategories as $navCategory)
                                            @php($isActiveCategory = request()->routeIs('categories.show') && $routeCategory && ($routeCategory->id === $navCategory->id || $routeCategory->parent_id === $navCategory->id))
                                            <a href="{{ route('categories.show', $navCategory) }}" class="catalog-header-menu-link {{ $isActiveCategory ? 'is-active' : '' }}">
                                                {{ $navCategory->name }}
                                            </a>
                                        @endforeach
                                    </nav>
                                </div>
                            </div>
                        @endif
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
