@extends('layouts.app')

@section('title', ($isManager ?? false) ? 'Заказы клиентов - МАЖОР' : 'История заказов - МАЖОР')

@section('content')
    @if ($orders->isEmpty())
        <div class="surface-card p-12 text-center">
            <h2 class="font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Заказов пока нет</h2>
            <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-slate-600">
                @if ($isManager ?? false)
                    Когда клиенты начнут отправлять корзины, здесь появится рабочая лента заказов.
                @else
                    Сформируйте первую корзину и отправьте ее менеджеру, чтобы история начала заполняться.
                @endif
            </p>
            <a href="{{ route('catalog.index') }}" class="catalog-buy-button mx-auto mt-6 w-fit">Перейти в каталог</a>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($orders as $order)
                @php($normalizedStatus = \App\Support\OrderStatuses::normalize($order->status))
                @php($statusLabel = $statusOptions[$normalizedStatus] ?? \App\Support\OrderStatuses::label($order->status))
                @php($placedAt = $order->placed_at?->format('d.m.Y H:i') ?? $order->created_at->format('d.m.Y H:i'))
                @php($summaryTotal = $order->total_amount !== null && (float) $order->total_amount > 0 ? \Illuminate\Support\Number::format((float) $order->total_amount, 2, locale: 'ru').' ₽' : 'По запросу')
                @php($summaryAddress = $order->customer_delivery_address ?: 'Адрес не указан')

                <details class="surface-card reveal-card catalog-order-collapsible">
                    <summary class="catalog-order-collapsible__summary">
                        <div class="catalog-order-collapsible__summary-main">
                            <div class="catalog-order-collapsible__title-row">
                                <h2 class="catalog-order-collapsible__number">{{ $order->number ?? 'Заказ' }}</h2>
                                <span class="catalog-status-chip">{{ $statusLabel }}</span>
                            </div>

                            <div class="catalog-order-collapsible__meta-grid">
                                <div class="catalog-order-collapsible__meta-item">
                                    <span>Время заказа</span>
                                    <strong>{{ $placedAt }}</strong>
                                </div>
                                <div class="catalog-order-collapsible__meta-item catalog-order-collapsible__meta-item--address">
                                    <span>Адрес</span>
                                    <strong>{{ \Illuminate\Support\Str::limit($summaryAddress, 86) }}</strong>
                                </div>
                                <div class="catalog-order-collapsible__meta-item">
                                    <span>Сумма</span>
                                    <strong>{{ $summaryTotal }}</strong>
                                </div>
                            </div>
                        </div>

                        <span class="catalog-order-collapsible__action">
                            <span class="catalog-order-collapsible__action-label catalog-order-collapsible__action-label--closed">Подробнее</span>
                            <span class="catalog-order-collapsible__action-label catalog-order-collapsible__action-label--open">Скрыть</span>
                        </span>
                    </summary>

                    <div class="catalog-order-collapsible__body">
                        <div class="catalog-order-workspace {{ ($isManager ?? false) ? 'is-manager' : '' }}">
                            <div class="catalog-order-main">
                                <div class="catalog-order-note">
                                    <strong>Адрес доставки:</strong> {{ $summaryAddress }}
                                </div>

                                @if ($order->comment)
                                    <div class="catalog-order-note">
                                        <strong>Комментарий клиента:</strong> {{ $order->comment }}
                                    </div>
                                @endif

                                @if ($order->manager_comment)
                                    <div class="catalog-order-note is-manager">
                                        <strong>Комментарий менеджера:</strong> {{ $order->manager_comment }}
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
                                                    @if ($item->product?->vendor_code)
                                                        Артикул {{ $item->product->vendor_code }}
                                                    @else
                                                        Каталог
                                                    @endif
                                                    @if ($item->measurement_value)
                                                        · {{ \Illuminate\Support\Str::limit(str_replace("\n", ' / ', $item->measurement_value), 42) }}
                                                    @endif
                                                </p>
                                            </div>

                                            <div class="catalog-order-item__qty">x{{ $item->quantity }}</div>

                                            <div class="catalog-order-item__price">
                                                @if ($item->unit_price !== null)
                                                    <p>{{ \Illuminate\Support\Number::format((float) $item->unit_price, 2, locale: 'ru') }} ₽</p>
                                                    <span>{{ \Illuminate\Support\Number::format((float) ($item->line_total ?? 0), 2, locale: 'ru') }} ₽</span>
                                                @else
                                                    <p>Цена по запросу</p>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            @if ($isManager ?? false)
                                <aside class="catalog-order-side">
                                    <form action="{{ route('orders.update', $order) }}" method="POST" class="catalog-order-manager-form">
                                        @csrf
                                        @method('PATCH')

                                        <label class="access-field">
                                            <span>Статус</span>
                                            <select name="status" class="catalog-clean-input">
                                                @foreach ($statusOptions as $value => $label)
                                                    <option value="{{ $value }}" @selected($normalizedStatus === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </label>

                                        <label class="access-field">
                                            <span>Комментарий менеджера</span>
                                            <textarea
                                                name="manager_comment"
                                                rows="6"
                                                class="catalog-clean-input min-h-[140px] resize-y"
                                                placeholder="Что уточнили, какой следующий шаг, когда связаться"
                                            >{{ old('manager_comment', $order->manager_comment) }}</textarea>
                                        </label>

                                        <button type="submit" class="action-button w-full justify-center">Сохранить статус</button>
                                    </form>

                                    <form action="{{ route('orders.export-1c', $order) }}" method="POST" class="catalog-order-manager-form mt-3">
                                        @csrf
                                        <button type="submit" class="ghost-button w-full justify-center">Выгрузить в 1С</button>
                                    </form>

                                    <p class="catalog-order-side__hint mt-3 text-xs text-slate-500">
                                        @if ($order->one_c_exported_at)
                                            Последняя выгрузка: {{ $order->one_c_exported_at->format('d.m.Y H:i') }}
                                        @else
                                            Еще не выгружен в 1С.
                                        @endif
                                    </p>
                                </aside>
                            @endif
                        </div>
                    </div>
                </details>
            @endforeach
        </div>

        <div class="surface-card mt-6 p-4 sm:p-5">
            {{ $orders->links() }}
        </div>
    @endif
@endsection
