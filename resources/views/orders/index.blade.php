@extends('layouts.app')

@section('title', ($isManager ?? false) ? 'Заказы клиентов - MAJOR' : 'История заказов - MAJOR')

@section('content')
    <section class="surface-card reveal-card catalog-page-hero p-6 sm:p-8">
        <div class="catalog-page-head">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">
                    {{ ($isManager ?? false) ? 'Заказы клиентов' : 'История заказов' }}
                </p>
                <h1 class="mt-2 font-['IBM_Plex_Sans'] text-4xl font-semibold tracking-tight text-slate-950">
                    {{ ($isManager ?? false) ? 'Работа с клиентскими заказами' : 'Все оформленные заявки' }}
                </h1>
                <p class="mt-4 max-w-3xl text-base leading-7 text-slate-600">
                    @if ($isManager ?? false)
                        Здесь менеджер видит все клиентские корзины, контактные данные, комментарии и текущие статусы,
                        а также может перевести заказ в работу, завершить его или оставить примечание клиенту.
                    @else
                        Здесь хранится история ваших оформленных заказов, их состав и комментарии менеджера по дальнейшей работе.
                    @endif
                </p>
            </div>

            <div class="catalog-list-header__stats">
                <div class="catalog-stat-box">
                    <span>Всего</span>
                    <strong>{{ $orderStats['total'] }}</strong>
                </div>
                <div class="catalog-stat-box">
                    <span>Новых</span>
                    <strong>{{ $orderStats['new'] }}</strong>
                </div>
                <div class="catalog-stat-box">
                    <span>В работе</span>
                    <strong>{{ $orderStats['processing'] }}</strong>
                </div>
            </div>
        </div>
    </section>

    @if ($orders->isEmpty())
        <div class="surface-card mt-6 p-12 text-center">
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
        <div class="mt-6 space-y-4">
            @foreach ($orders as $order)
                @php($statusLabel = $statusOptions[$order->status] ?? ucfirst($order->status))

                <article class="surface-card reveal-card p-6 sm:p-7">
                    <div class="catalog-order-workspace {{ ($isManager ?? false) ? 'is-manager' : '' }}">
                        <div class="catalog-order-main">
                            <div class="catalog-order-card__head">
                                <div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <h2 class="font-['IBM_Plex_Sans'] text-2xl font-semibold text-slate-950">{{ $order->number ?? 'Заказ' }}</h2>
                                        <span class="catalog-status-chip">{{ $statusLabel }}</span>
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

                            <div class="catalog-order-client">
                                <div class="catalog-order-client__card">
                                    <span>Клиент</span>
                                    <strong>{{ $order->customer_name ?? $order->user?->name ?? 'Не указан' }}</strong>
                                    <small>{{ $order->customer_company ?? $order->user?->company ?? 'Компания не указана' }}</small>
                                </div>
                                <div class="catalog-order-client__card">
                                    <span>Контакт</span>
                                    <strong>{{ $order->customer_contact_person ?: 'Не указан' }}</strong>
                                    <small>{{ $order->customer_phone ?: 'Телефон не указан' }}</small>
                                </div>
                                <div class="catalog-order-client__card">
                                    <span>Связь</span>
                                    <strong>{{ $order->customer_email ?? $order->user?->email ?? 'Email не указан' }}</strong>
                                    <small>{{ $order->customer_telegram ?: 'Telegram не указан' }}</small>
                                </div>
                                <div class="catalog-order-client__card">
                                    <span>Адрес / доставка</span>
                                    <strong>{{ $order->customer_delivery_address ?: 'Не указан' }}</strong>
                                    <small>{{ $order->user?->login ? 'Логин: '.$order->user->login : 'Без отдельного логина' }}</small>
                                </div>
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
                                                <option value="{{ $value }}" @selected($order->status === $value)>{{ $label }}</option>
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
                            </aside>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <div class="surface-card mt-6 p-4 sm:p-5">
            {{ $orders->links() }}
        </div>
    @endif
@endsection
