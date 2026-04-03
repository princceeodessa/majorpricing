@extends('layouts.app')

@section('title', 'История заказов - MAJPR')

@section('content')
    <section class="surface-card reveal-card catalog-page-hero p-6 sm:p-8">
        <div class="catalog-page-head">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">История заказов</p>
                <h1 class="mt-2 font-['IBM_Plex_Sans'] text-4xl font-semibold tracking-tight text-slate-950">Заказы кабинета</h1>
                <p class="mt-4 max-w-3xl text-base leading-7 text-slate-600">
                    Здесь хранится история всех оформленных корзин: состав, цены на момент заказа и комментарии для менеджера.
                </p>
            </div>

            <div class="catalog-list-header__stats">
                <div class="catalog-stat-box">
                    <span>Заказов</span>
                    <strong>{{ $orders->total() }}</strong>
                </div>
                <div class="catalog-stat-box">
                    <span>Последний статус</span>
                    <strong>{{ $orders->first()?->status === 'new' ? 'Новый' : 'В работе' }}</strong>
                </div>
                <div class="catalog-stat-box">
                    <span>Корзина</span>
                    <strong>{{ $headerCartCount ?? 0 }}</strong>
                </div>
            </div>
        </div>
    </section>

    @if ($orders->isEmpty())
        <div class="surface-card mt-6 p-12 text-center">
            <h2 class="font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Заказов пока нет</h2>
            <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-slate-600">
                Сформируйте первую корзину и подтвердите её, чтобы история заказов начала заполняться.
            </p>
            <a href="{{ route('catalog.index') }}" class="catalog-buy-button mx-auto mt-6 w-fit">Перейти в каталог</a>
        </div>
    @else
        <div class="mt-6 space-y-4">
            @foreach ($orders as $order)
                <article class="surface-card reveal-card p-6 sm:p-7">
                    <div class="catalog-order-card">
                        <div class="catalog-order-card__head">
                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <h2 class="font-['IBM_Plex_Sans'] text-2xl font-semibold text-slate-950">{{ $order->number ?? 'Заказ' }}</h2>
                                    <span class="catalog-status-chip">{{ $order->status === 'new' ? 'Новый' : 'В работе' }}</span>
                                </div>
                                <p class="mt-2 text-sm leading-6 text-slate-600">
                                    {{ $order->placed_at?->format('d.m.Y H:i') ?? $order->created_at->format('d.m.Y H:i') }}
                                    @if ($order->price_profile_name)
                                        · {{ $order->price_profile_name }}
                                    @endif
                                </p>
                            </div>

                            <div class="catalog-order-card__totals">
                                <div class="catalog-summary-row">
                                    <span>Позиций</span>
                                    <strong>{{ $order->items_count }}</strong>
                                </div>
                                <div class="catalog-summary-row is-total">
                                    <span>Сумма</span>
                                    <strong>
                                        @if ($order->total_amount !== null && (float) $order->total_amount > 0)
                                            {{ \Illuminate\Support\Number::format((float) $order->total_amount, 2, locale: 'ru') }} ₽
                                        @else
                                            По запросу
                                        @endif
                                    </strong>
                                </div>
                            </div>
                        </div>

                        @if ($order->comment)
                            <div class="catalog-order-note">
                                {{ $order->comment }}
                            </div>
                        @endif

                        <div class="catalog-order-items">
                            @foreach ($order->items as $item)
                                <div class="catalog-order-item">
                                    <div>
                                        @if ($item->product_slug && $item->product)
                                            <a href="{{ route('products.show', ['product' => $item->product_slug]) }}" class="catalog-order-item__title">{{ $item->product_title }}</a>
                                        @else
                                            <p class="catalog-order-item__title">{{ $item->product_title }}</p>
                                        @endif

                                        <p class="catalog-order-item__meta">
                                            {{ $item->source_sheet ?? 'Каталог' }}
                                            @if ($item->measurement_value)
                                                · {{ \Illuminate\Support\Str::limit(str_replace("\n", ' / ', $item->measurement_value), 42) }}
                                            @endif
                                        </p>
                                    </div>

                                    <div class="catalog-order-item__qty">x{{ $item->quantity }}</div>

                                    <div class="catalog-order-item__price">
                                        @if ($item->unit_price !== null)
                                            <p>{{ \Illuminate\Support\Number::format((float) $item->unit_price, 2, locale: 'ru') }} ₽</p>
                                            <span>
                                                {{ \Illuminate\Support\Number::format((float) ($item->line_total ?? 0), 2, locale: 'ru') }} ₽
                                            </span>
                                        @else
                                            <p>Цена по запросу</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="surface-card mt-6 p-4 sm:p-5">
            {{ $orders->links() }}
        </div>
    @endif
@endsection
