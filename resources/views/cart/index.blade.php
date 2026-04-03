@extends('layouts.app')

@section('title', 'Корзина - MAJOR')

@section('content')
    <section class="surface-card reveal-card catalog-page-hero p-6 sm:p-8">
        <div class="catalog-page-head">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Корзина</p>
                <h1 class="mt-2 font-['IBM_Plex_Sans'] text-4xl font-semibold tracking-tight text-slate-950">Текущий заказ</h1>
                <p class="mt-4 max-w-3xl text-base leading-7 text-slate-600">
                    Здесь собираются позиции из закрытого каталога. После подтверждения корзина превращается в заказ и уходит в историю.
                </p>
            </div>

            <div class="catalog-list-header__stats">
                <div class="catalog-stat-box">
                    <span>Позиций</span>
                    <strong>{{ $summary['items_count'] }}</strong>
                </div>
                <div class="catalog-stat-box">
                    <span>Штук</span>
                    <strong>{{ $summary['total_quantity'] }}</strong>
                </div>
                <div class="catalog-stat-box">
                    <span>Сумма</span>
                    <strong>
                        @if ($summary['total_amount'] > 0)
                            {{ \Illuminate\Support\Number::format($summary['total_amount'], 2, locale: 'ru') }} ₽
                        @else
                            По запросу
                        @endif
                    </strong>
                </div>
            </div>
        </div>
    </section>

    @if ($cartItems->isEmpty())
        <div class="surface-card mt-6 p-12 text-center">
            <h2 class="font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Корзина пока пуста</h2>
            <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-slate-600">
                Добавьте товары из карточек каталога, и здесь появится рабочая подборка для отправки в заказ.
            </p>
            <a href="{{ route('catalog.index') }}" class="catalog-buy-button mx-auto mt-6 w-fit">Вернуться в каталог</a>
        </div>
    @else
        <section class="catalog-cart-layout mt-6">
            <div class="space-y-4">
                @foreach ($cartItems as $cartItem)
                    @php($product = $cartItem->product)
                    @php($price = $cartItem->resolved_price)

                    <article class="surface-card reveal-card p-5 sm:p-6">
                        <div class="catalog-cart-item">
                            <div class="catalog-cart-item__visual">
                                @if ($product?->image_path)
                                    <img src="{{ asset($product->image_path) }}" alt="{{ $product->title }}" class="catalog-cart-item__image">
                                @else
                                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($product?->title ?? 'PR', 0, 2)) }}
                                @endif
                            </div>

                            <div class="catalog-cart-item__content">
                                <div>
                                    <p class="catalog-cart-item__eyebrow">{{ $product?->category?->name ?? 'Каталог' }}</p>
                                    @if ($product)
                                        <a href="{{ route('products.show', $product) }}" class="catalog-cart-item__title">{{ $product->title }}</a>
                                    @else
                                        <p class="catalog-cart-item__title">Товар из каталога</p>
                                    @endif

                                    <p class="catalog-cart-item__meta">
                                        {{ $product?->source_sheet ?? 'Закрытый каталог' }}
                                        @if ($product?->measurement_value)
                                            · {{ \Illuminate\Support\Str::limit(str_replace("\n", ' / ', $product->measurement_value), 42) }}
                                        @endif
                                    </p>
                                </div>

                                <div class="catalog-cart-item__controls">
                                    <form action="{{ route('cart.items.update', $cartItem) }}" method="POST" class="catalog-cart-qty-form">
                                        @csrf
                                        @method('PATCH')

                                        <input
                                            type="number"
                                            min="1"
                                            max="999"
                                            name="quantity"
                                            value="{{ $cartItem->quantity }}"
                                            class="catalog-clean-input"
                                        >
                                        <button type="submit" class="catalog-reset-button">Обновить</button>
                                    </form>

                                    <form action="{{ route('cart.items.destroy', $cartItem) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="catalog-inline-action">Удалить</button>
                                    </form>
                                </div>
                            </div>

                            <div class="catalog-cart-item__price">
                                <p class="catalog-cart-item__price-label">{{ $price?->label ?? (auth()->user()->priceProfile?->price_label ?? 'Цена') }}</p>
                                @if ($cartItem->resolved_unit_amount !== null)
                                    <p class="catalog-cart-item__price-value">
                                        {{ \Illuminate\Support\Number::format((float) $cartItem->resolved_unit_amount, 2, locale: 'ru') }} ₽
                                    </p>
                                    <p class="catalog-cart-item__line-total">
                                        Итого: {{ \Illuminate\Support\Number::format((float) $cartItem->resolved_line_amount, 2, locale: 'ru') }} ₽
                                    </p>
                                @else
                                    <p class="catalog-cart-item__price-value catalog-cart-item__price-value--empty">Цена по запросу</p>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <aside class="surface-card reveal-card p-6 sm:p-7 lg:sticky lg:top-6">
                <form action="{{ route('cart.checkout') }}" method="POST" class="space-y-6">
                    @csrf

                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Оформление</p>
                        <h2 class="mt-2 font-['IBM_Plex_Sans'] text-3xl font-semibold tracking-tight text-slate-950">Подтвердить заказ</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">
                            После подтверждения корзина очистится, а заказ появится в истории кабинета.
                        </p>
                    </div>

                    <div class="space-y-3">
                        <div class="catalog-summary-row">
                            <span>Позиций</span>
                            <strong>{{ $summary['items_count'] }}</strong>
                        </div>
                        <div class="catalog-summary-row">
                            <span>Количество</span>
                            <strong>{{ $summary['total_quantity'] }}</strong>
                        </div>
                        <div class="catalog-summary-row">
                            <span>С ценой</span>
                            <strong>{{ $summary['priced_items_count'] }}</strong>
                        </div>
                        @if ($summary['unpriced_items_count'] > 0)
                            <div class="catalog-summary-row">
                                <span>По запросу</span>
                                <strong>{{ $summary['unpriced_items_count'] }}</strong>
                            </div>
                        @endif
                        <div class="catalog-summary-row is-total">
                            <span>Итого</span>
                            <strong>
                                @if ($summary['total_amount'] > 0)
                                    {{ \Illuminate\Support\Number::format($summary['total_amount'], 2, locale: 'ru') }} ₽
                                @else
                                    По запросу
                                @endif
                            </strong>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label for="comment" class="catalog-filter-title">Комментарий к заказу</label>
                        <textarea
                            id="comment"
                            name="comment"
                            rows="4"
                            placeholder="Например: отгрузка по объекту, резерв до даты, комментарий менеджеру"
                            class="catalog-clean-input min-h-[120px] resize-y"
                        >{{ old('comment') }}</textarea>
                    </div>

                    <button type="submit" class="catalog-buy-button w-full justify-center">Оформить заказ</button>

                    <a href="{{ route('orders.index') }}" class="catalog-reset-button w-full justify-center">Открыть историю заказов</a>
                </form>
            </aside>
        </section>
    @endif
@endsection
