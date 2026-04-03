@extends('layouts.app')

@section('title', 'Личный кабинет MAJPR')

@section('content')
    <section class="catalog-account-grid">
        <div class="surface-card reveal-card catalog-account-hero">
            <span class="soft-badge">Личный кабинет</span>
            <h1 class="catalog-account-hero__title">
                {{ auth()->user()->company ?? auth()->user()->name }}
            </h1>
            <p class="catalog-account-hero__text">
                Отдельный рабочий экран с вашим прайс-профилем, быстрыми действиями, историей заказов и персональными подборками по каталогу.
            </p>

            <div class="catalog-account-hero__meta">
                <div class="catalog-account-meta-card">
                    <span>Логин</span>
                    <strong>{{ auth()->user()->login }}</strong>
                </div>
                <div class="catalog-account-meta-card">
                    <span>Email</span>
                    <strong>{{ auth()->user()->email }}</strong>
                </div>
                <div class="catalog-account-meta-card">
                    <span>Доступ</span>
                    <strong>Закрытый кабинет</strong>
                </div>
            </div>

            <div class="catalog-account-hero__actions">
                <a href="{{ route('catalog.index') }}" class="action-button">Перейти в каталог</a>
                <a href="{{ route('favorites.index') }}" class="catalog-home-banner__ghost">Избранное</a>
                <a href="{{ route('cart.index') }}" class="catalog-home-banner__ghost">Корзина</a>
            </div>
        </div>

        <aside class="surface-card reveal-card catalog-account-sidebar">
            <span class="soft-badge">Ваш профиль</span>

            <div class="catalog-account-sidebar__stack">
                <div class="stat-card">
                    <span>Профиль цен</span>
                    <strong>{{ $profile?->name ?? 'Базовый прайс' }}</strong>
                </div>
                <div class="stat-card">
                    <span>Колонка прайса</span>
                    <strong>{{ $profile?->price_label ?? 'Цена 1' }}</strong>
                </div>
                <div class="stat-card">
                    <span>Категорий</span>
                    <strong>{{ $accountStats['rootCategories'] }}</strong>
                </div>
            </div>

            <div class="catalog-account-sidebar__summary">
                <div class="catalog-account-kpi">
                    <span>Заказов</span>
                    <strong>{{ $accountStats['ordersCount'] }}</strong>
                </div>
                <div class="catalog-account-kpi">
                    <span>Избранное</span>
                    <strong>{{ $accountStats['favoritesCount'] }}</strong>
                </div>
                <div class="catalog-account-kpi">
                    <span>Товаров в корзине</span>
                    <strong>{{ $accountStats['cartQuantity'] }}</strong>
                </div>
                <div class="catalog-account-kpi">
                    <span>Сумма заказов</span>
                    <strong>{{ number_format($accountStats['totalSpent'], 0, ',', ' ') }} ₽</strong>
                </div>
            </div>

            <div class="catalog-home-links">
                <a href="{{ route('orders.index') }}" class="catalog-home-link">
                    <span>История заказов</span>
                    <strong>{{ $headerOrdersCount ?? 0 }}</strong>
                </a>
                <a href="{{ route('favorites.index') }}" class="catalog-home-link">
                    <span>Избранные товары</span>
                    <strong>{{ $headerFavoritesCount ?? 0 }}</strong>
                </a>
                <a href="{{ route('cart.index') }}" class="catalog-home-link">
                    <span>Текущая корзина</span>
                    <strong>{{ $headerCartCount ?? 0 }}</strong>
                </a>
            </div>
        </aside>
    </section>

    <section class="catalog-account-section mt-10">
        <div class="catalog-section-head">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Заказы</p>
                <h2 class="mt-2 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Последние продажи</h2>
            </div>
            <p class="max-w-xl text-sm leading-6 text-slate-600">
                Последние оформленные заказы по вашему кабинету. Здесь удобно быстро проверить статус и сумму без перехода в общую историю.
            </p>
        </div>

        <div class="catalog-account-orders mt-5">
            @forelse ($recentOrders as $order)
                <article class="surface-card reveal-card catalog-account-order">
                    <div class="catalog-account-order__head">
                        <div>
                            <span class="soft-badge">Заказ</span>
                            <h3>{{ $order->number }}</h3>
                        </div>
                        <div class="catalog-account-order__status">
                            <span>{{ $order->status }}</span>
                            <strong>{{ number_format((float) $order->total_amount, 2, ',', ' ') }} ₽</strong>
                        </div>
                    </div>

                    <div class="catalog-account-order__meta">
                        <span>{{ optional($order->placed_at)->format('d.m.Y H:i') ?? 'Не указано' }}</span>
                        <span>{{ $order->items_count }} поз.</span>
                        <span>Оплата: {{ $order->payment_status }}</span>
                    </div>

                    @if ($order->items->isNotEmpty())
                        <div class="catalog-account-order__items">
                            @foreach ($order->items->take(3) as $item)
                                <div class="catalog-account-order__item">
                                    <strong>{{ $item->product_title }}</strong>
                                    <span>{{ $item->quantity }} шт. • {{ number_format((float) $item->line_total, 2, ',', ' ') }} ₽</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </article>
            @empty
                <div class="surface-card p-8 text-center text-slate-600">
                    Заказов пока нет. После оформления они появятся здесь.
                </div>
            @endforelse
        </div>
    </section>

    <section class="catalog-account-columns mt-10">
        <div class="surface-card reveal-card catalog-account-panel">
            <div class="catalog-section-head">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Подборка</p>
                    <h2 class="mt-2 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Избранное</h2>
                </div>
            </div>

            <div class="catalog-account-products">
                @forelse ($favoriteItems as $favoriteItem)
                    @php($product = $favoriteItem->product)
                    @php($price = $product?->priceForProfile($profile))

                    @if ($product)
                        <a href="{{ route('products.show', $product) }}" class="catalog-account-product">
                            <div class="catalog-account-product__media">
                                @if ($product->image_path)
                                    <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->title }}">
                                @else
                                    <span>{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($product->title, 0, 2)) }}</span>
                                @endif
                            </div>
                            <div class="catalog-account-product__body">
                                <span>{{ $product->category?->name ?? 'Каталог' }}</span>
                                <strong>{{ $product->title }}</strong>
                                <small>{{ $price?->display_value ?? $product->price_preview ?? 'Цена по запросу' }} ₽</small>
                            </div>
                        </a>
                    @endif
                @empty
                    <div class="catalog-account-empty">Пока нет избранных товаров.</div>
                @endforelse
            </div>
        </div>

        <div class="surface-card reveal-card catalog-account-panel">
            <div class="catalog-section-head">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Текущая работа</p>
                    <h2 class="mt-2 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Корзина</h2>
                </div>
            </div>

            <div class="catalog-account-products">
                @forelse ($cartItems as $cartItem)
                    @php($product = $cartItem->product)
                    @php($price = $product?->priceForProfile($profile))

                    @if ($product)
                        <a href="{{ route('products.show', $product) }}" class="catalog-account-product">
                            <div class="catalog-account-product__media">
                                @if ($product->image_path)
                                    <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->title }}">
                                @else
                                    <span>{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($product->title, 0, 2)) }}</span>
                                @endif
                            </div>
                            <div class="catalog-account-product__body">
                                <span>{{ $product->category?->name ?? 'Каталог' }}</span>
                                <strong>{{ $product->title }}</strong>
                                <small>{{ $cartItem->quantity }} шт. • {{ $price?->display_value ?? $product->price_preview ?? 'Цена по запросу' }} ₽</small>
                            </div>
                        </a>
                    @endif
                @empty
                    <div class="catalog-account-empty">Корзина пуста. Добавьте товары из каталога.</div>
                @endforelse
            </div>
        </div>
    </section>
@endsection
