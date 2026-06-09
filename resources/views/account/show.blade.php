@extends('layouts.app')

@php
    $currentUser = auth()->user();
    $canManageClients = $currentUser?->canManageClients() ?? false;
    $isAdmin = $currentUser?->isAdmin() ?? false;
@endphp

@section('title', ($canManageClients ? ($isAdmin ? 'Панель администратора' : 'Клиенты и заявки') : 'Личный кабинет').' - МАЖОР')

@section('content')
    {{-- Карточка установки PWA. Показывается всем (клиентам, менеджерам, админам).
         JS включает её только если браузер поддерживает install и приложение ещё не установлено. --}}
    <article
        id="pwa-install-card"
        class="surface-card reveal-card pwa-install-card"
        hidden
    >
        <div class="pwa-install-card__icon" aria-hidden="true">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="5" y="2" width="14" height="20" rx="3"/>
                <path d="M12 18h.01"/>
            </svg>
        </div>
        <div class="pwa-install-card__body">
            <h2 class="pwa-install-card__title">Установить приложение МАЖОР</h2>
            <p class="pwa-install-card__text">
                Добавьте каталог на главный экран телефона: запуск в один тап, push-уведомления о статусах заявок и быстрый доступ к корзине без браузера.
            </p>
            <div class="pwa-install-card__actions">
                <button type="button" id="pwa-install-trigger" class="pwa-install-card__cta">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3v12"/>
                        <path d="m7 10 5 5 5-5"/>
                        <path d="M5 21h14"/>
                    </svg>
                    <span>Установить</span>
                </button>
                <span class="pwa-install-card__ios-hint" hidden>
                    На iPhone: <b>Поделиться</b> → <b>На экран «Домой»</b>.
                </span>
            </div>
        </div>
    </article>

    <script>
        (function () {
            var card = document.getElementById('pwa-install-card');
            var btn = document.getElementById('pwa-install-trigger');
            var iosHint = card ? card.querySelector('.pwa-install-card__ios-hint') : null;
            if (!card || !btn) return;

            function isStandalone() {
                return window.matchMedia('(display-mode: standalone)').matches
                    || window.navigator.standalone === true;
            }

            function isiOS() {
                return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
            }

            function show() {
                card.hidden = false;
            }

            function hide() {
                card.hidden = true;
            }

            // Уже стоит как PWA — не показываем
            if (isStandalone() || (window.__pwa && window.__pwa.installed)) {
                hide();
                return;
            }

            // Android Chrome: показываем кнопку, когда событие готово
            if (window.__pwa && window.__pwa.prompt) {
                show();
            }
            document.addEventListener('pwa:ready', show);

            // iOS Safari не даёт programmatic install — даём текстовую подсказку
            if (isiOS() && iosHint) {
                iosHint.hidden = false;
                btn.hidden = true;
                show();
            }

            btn.addEventListener('click', function () {
                var deferred = window.__pwa && window.__pwa.prompt;
                if (!deferred) {
                    hide();
                    return;
                }
                deferred.prompt();
                deferred.userChoice.then(function (choice) {
                    window.__pwa.prompt = null;
                    hide();
                });
            });

            document.addEventListener('pwa:installed', hide);
        })();
    </script>

    @if ($canManageClients)
        @php
            $selectedAccountType = old('account_type', 'client');
            $createContactPeople = old('contact_people');
            $createMessengers = old('messengers');
            $createContactPeople = is_array($createContactPeople) && count(array_filter($createContactPeople, fn ($item) => filled($item))) > 0 ? array_values($createContactPeople) : [''];
            $createMessengers = is_array($createMessengers) && count(array_filter($createMessengers, fn ($item) => filled($item))) > 0 ? array_values($createMessengers) : [''];
        @endphp

        <section class="catalog-account-grid">
            <article class="surface-card reveal-card catalog-account-hero">
                <div class="space-y-6">
                    <div class="space-y-4">
                        <span class="soft-badge">{{ $isAdmin ? 'Администрирование' : 'Работа с клиентами' }}</span>
                        <div class="space-y-3">
                            <h1 class="catalog-account-hero__title">{{ $isAdmin ? 'Панель администратора' : 'Клиенты и заявки' }}</h1>
                            <p class="catalog-account-hero__text">
                                @if ($isAdmin)
                                    Администратор управляет менеджерами, подтверждает новые регистрации, контролирует клиентскую базу и доступ к системным разделам.
                                @else
                                    Менеджер работает только со своими клиентами: создает доступ, подтверждает входящие заявки и ведет переписку по заказам.
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="catalog-account-hero__meta">
                        <div class="catalog-account-kpi">
                            <span>Клиентов</span>
                            <strong>{{ $managementStats['totalUsers'] }}</strong>
                        </div>
                        <div class="catalog-account-kpi">
                            <span>Активных</span>
                            <strong>{{ $managementStats['activeUsers'] }}</strong>
                        </div>
                        <div class="catalog-account-kpi">
                            <span>Заявок</span>
                            <strong>{{ $managementStats['pendingRequests'] }}</strong>
                        </div>
                    </div>
                </div>

                <div class="catalog-account-hero__actions">
                    <a href="{{ route('manager.chats.index') }}" class="action-button">Открыть чаты</a>

                    @if ($isAdmin)
                        <a href="{{ route('admin.onec.show') }}" class="ghost-button">Диагностика 1С</a>
                        <a href="{{ route('admin.catalog.visibility.index') }}" class="ghost-button">Видимость каталога</a>
                        <a href="{{ route('admin.products.images.index') }}" class="ghost-button">Фото товаров</a>
                        <a href="{{ route('admin.products.variants.index') }}" class="ghost-button">Варианты товаров</a>
                        <a href="{{ route('admin.products.related.index') }}" class="ghost-button">Сопутствующие товары</a>
                    @endif

                    <a href="{{ route('orders.index') }}" class="ghost-button">Заказы клиентов</a>
                </div>
            </article>

            <aside class="surface-card reveal-card catalog-account-sidebar">
                <div class="catalog-account-sidebar__summary">
                    <div class="catalog-account-meta-card">
                        <span>Новых заказов</span>
                        <strong>{{ $managementStats['newOrders'] }}</strong>
                    </div>
                    <div class="catalog-account-meta-card">
                        <span>В работе</span>
                        <strong>{{ $managementStats['processingOrders'] }}</strong>
                    </div>
                    <div class="catalog-account-meta-card">
                        <span>Отключено</span>
                        <strong>{{ $managementStats['disabledUsers'] }}</strong>
                    </div>
                    <div class="catalog-account-meta-card">
                        <span>Менеджеров</span>
                        <strong>{{ $managers->count() }}</strong>
                    </div>
                </div>

                @if ($isAdmin)
                    <div class="catalog-account-sidebar__stack">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Команда</p>
                                <h2 class="mt-2 font-['IBM_Plex_Sans'] text-2xl font-semibold tracking-tight text-slate-950">Менеджеры</h2>
                            </div>
                            <span class="soft-badge soft-badge--dark">{{ $managers->count() }}</span>
                        </div>

                        <div class="space-y-3">
                            @forelse ($managers as $manager)
                                <article class="rounded-[24px] border border-slate-200 bg-white/90 p-4 shadow-[0_18px_35px_-28px_rgba(15,23,42,0.18)]">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h3 class="text-base font-semibold text-slate-950">{{ $manager->name }}</h3>
                                            @if ($manager->city)
                                                <p class="mt-1 text-sm font-semibold text-slate-700">Город: {{ $manager->city }}</p>
                                            @endif
                                            <p class="mt-1 break-all text-sm text-slate-500">{{ $manager->email ?: 'Email не указан' }}</p>
                                            @if ($manager->phone)
                                                <p class="mt-2 text-sm font-medium text-slate-700">{{ $manager->phone }}</p>
                                            @endif
                                        </div>

                                        <div class="text-right">
                                            <span class="inline-flex min-h-8 items-center rounded-full px-3 text-[11px] font-bold uppercase tracking-[0.2em] {{ $manager->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                                {{ $manager->is_active ? 'Активен' : 'Выключен' }}
                                            </span>
                                            <p class="mt-3 text-sm font-semibold text-slate-900">{{ $manager->managed_clients_count }} клиентов</p>
                                        </div>
                                    </div>

                                    @if ($manager->messengers)
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach ((array) $manager->messengers as $messenger)
                                                <span class="inline-flex min-h-8 items-center rounded-full border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-600">{{ $messenger }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </article>
                            @empty
                                <div class="catalog-account-empty">
                                    Менеджеры еще не созданы.
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endif
            </aside>
        </section>

        <section class="catalog-account-columns mt-6">
            <article class="surface-card reveal-card catalog-account-panel access-user-create">
                <div class="catalog-page-head">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Доступ</p>
                        <h2 class="access-user-create__title mt-2" @if ($isAdmin) data-account-role-heading @endif>
                            {{ $isAdmin ? ($selectedAccountType === 'manager' ? 'Создать менеджера' : 'Создать клиента') : 'Создать клиента' }}
                        </h2>
                        <p class="access-user-create__text mt-3" @if ($isAdmin) data-account-role-description @endif>
                            @if ($isAdmin)
                                {{ $selectedAccountType === 'manager'
                                    ? 'Администратор создает менеджера, который будет работать только со своими клиентами и чатами. Системные разделы 1С остаются только у администратора.'
                                    : 'Администратор создает клиентский кабинет, назначает ответственного менеджера и сразу подготавливает контактные данные для работы с заказами.' }}
                            @else
                                Менеджер создает доступ клиенту и сразу привязывает его к себе.
                            @endif
                        </p>
                    </div>
                </div>

                @if ($isAdmin)
                    <div class="catalog-account-role-switch mt-6">
                        <button
                            type="button"
                            class="catalog-account-role-switch__button {{ $selectedAccountType === 'client' ? 'is-active' : '' }}"
                            data-account-role-preset="client"
                            aria-pressed="{{ $selectedAccountType === 'client' ? 'true' : 'false' }}"
                        >
                            <span class="catalog-account-role-switch__eyebrow">Клиент</span>
                            <strong>Создать клиента</strong>
                            <small>Доступ в каталог, корзину и заказы с привязкой к менеджеру.</small>
                        </button>

                        <button
                            type="button"
                            class="catalog-account-role-switch__button {{ $selectedAccountType === 'manager' ? 'is-active' : '' }}"
                            data-account-role-preset="manager"
                            aria-pressed="{{ $selectedAccountType === 'manager' ? 'true' : 'false' }}"
                        >
                            <span class="catalog-account-role-switch__eyebrow">Менеджер</span>
                            <strong>Создать менеджера</strong>
                            <small>Работа с клиентами, чатами и подтверждением регистраций без доступа к системе.</small>
                        </button>
                    </div>
                @endif

                <form
                    action="{{ route('manager.users.store') }}"
                    method="POST"
                    class="access-user-create__form mt-6"
                    @if ($isAdmin)
                        data-account-role-form
                        data-client-title="Создать клиента"
                        data-manager-title="Создать менеджера"
                        data-client-description="Администратор создает клиентский кабинет, назначает ответственного менеджера и сразу подготавливает контактные данные для работы с заказами."
                        data-manager-description="Администратор создает менеджера, который будет работать только со своими клиентами и чатами. Системные разделы 1С остаются только у администратора."
                        data-client-submit="Создать клиента"
                        data-manager-submit="Создать менеджера"
                    @endif
                >
                    @csrf

                    @if ($isAdmin)
                        <div class="access-user-create__grid">
                            <label class="access-field access-field--full">
                                <span>Тип доступа</span>
                                <select name="account_type" data-account-role-select>
                                    <option value="client" @selected($selectedAccountType === 'client')>Клиент</option>
                                    <option value="manager" @selected($selectedAccountType === 'manager')>Менеджер</option>
                                </select>
                            </label>
                        </div>
                    @else
                        <input type="hidden" name="account_type" value="client">
                    @endif

                    @if ($isAdmin)
                        <div class="access-user-create__grid mt-4" data-account-role-section="manager">
                            <label class="access-field access-field--full">
                                <span>Город менеджера</span>
                                <select name="price_profile_id">
                                    <option value="">Выберите город</option>
                                    @foreach ($priceProfiles as $profile)
                                        <option value="{{ $profile->id }}" @selected((string) old('price_profile_id') === (string) $profile->id)>
                                            {{ $profile->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    @endif

                    <div class="access-user-create__grid">
                        <label class="access-field">
                            <span>Имя</span>
                            <input type="text" name="name" value="{{ old('name') }}" placeholder="Иван Петров">
                        </label>

                        <label class="access-field">
                            <span>Компания</span>
                            <input type="text" name="company" value="{{ old('company') }}" placeholder="ООО МАЖОР">
                        </label>

                        <label class="access-field">
                            <span>Телефон</span>
                            <input type="text" name="phone" value="{{ old('phone') }}" placeholder="+7 999 000-00-00">
                        </label>

                        <label class="access-field">
                            <span>Логин</span>
                            <input type="text" name="login" value="{{ old('login') }}" placeholder="major_client">
                        </label>

                        <label class="access-field">
                            <span>Email <small data-account-role-section="manager">не обязателен для менеджера</small></span>
                            <input type="email" name="email" value="{{ old('email') }}" placeholder="client@example.com">
                        </label>

                        <label class="access-field">
                            <span>Пароль</span>
                            <input type="password" name="password" placeholder="Минимум 8 символов">
                        </label>

                        <label class="access-field">
                            <span>Подтверждение пароля</span>
                            <input type="password" name="password_confirmation" placeholder="Повторите пароль">
                        </label>

                        <label class="flex items-center gap-3 rounded-[24px] border border-slate-200 bg-white px-5 py-4 text-sm font-semibold text-slate-700">
                            <input type="checkbox" name="is_active" value="1" class="h-4 w-4 accent-[var(--brand)]" @checked(old('is_active', '1'))>
                            Активировать сразу после создания
                        </label>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2" data-account-role-section="client">
                        @if ($isAdmin)
                            <label class="access-field">
                                <span>Назначить менеджера</span>
                                <select name="manager_id">
                                    <option value="">Выберите менеджера</option>
                                    @foreach ($availableManagers as $managerOption)
                                        <option value="{{ $managerOption->id }}" @selected((string) old('manager_id') === (string) $managerOption->id)>
                                            {{ $managerOption->name }}@if ($managerOption->city) — {{ $managerOption->city }}@elseif ($managerOption->email) — {{ $managerOption->email }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        @endif

                        <label class="access-field {{ $isAdmin ? '' : 'md:col-span-2' }}">
                            <span>Основной адрес</span>
                            <input type="text" name="delivery_address" value="{{ old('delivery_address') }}" placeholder="Город, улица, объект, комментарий по доставке">
                        </label>
                    </div>

                    <div class="mt-4 space-y-4" data-account-role-section="client">
                        <div class="catalog-repeatable" data-repeatable>
                            <div class="catalog-repeatable__head">
                                <span>Контактные лица</span>
                                <button type="button" class="catalog-inline-action" data-repeatable-add>+ Добавить</button>
                            </div>

                            <div class="catalog-repeatable__items" data-repeatable-items>
                                @foreach ($createContactPeople as $contactPerson)
                                    <div class="catalog-repeatable__item" data-repeatable-row>
                                        <input type="text" name="contact_people[]" value="{{ $contactPerson }}" placeholder="Контакт по заявкам" class="catalog-clean-input">
                                        <button type="button" class="catalog-inline-action" data-repeatable-remove>Удалить</button>
                                    </div>
                                @endforeach
                            </div>

                            <template data-repeatable-template>
                                <div class="catalog-repeatable__item" data-repeatable-row>
                                    <input type="text" name="contact_people[]" value="" placeholder="Контакт по заявкам" class="catalog-clean-input">
                                    <button type="button" class="catalog-inline-action" data-repeatable-remove>Удалить</button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div class="catalog-repeatable" data-repeatable>
                            <div class="catalog-repeatable__head">
                                <span>Мессенджеры</span>
                                <button type="button" class="catalog-inline-action" data-repeatable-add>+ Добавить</button>
                            </div>

                            <div class="catalog-repeatable__items" data-repeatable-items>
                                @foreach ($createMessengers as $messenger)
                                    <div class="catalog-repeatable__item" data-repeatable-row>
                                        <input type="text" name="messengers[]" value="{{ $messenger }}" placeholder="@telegram или WhatsApp" class="catalog-clean-input">
                                        <button type="button" class="catalog-inline-action" data-repeatable-remove>Удалить</button>
                                    </div>
                                @endforeach
                            </div>

                            <template data-repeatable-template>
                                <div class="catalog-repeatable__item" data-repeatable-row>
                                    <input type="text" name="messengers[]" value="" placeholder="@telegram или WhatsApp" class="catalog-clean-input">
                                    <button type="button" class="catalog-inline-action" data-repeatable-remove>Удалить</button>
                                </div>
                            </template>
                        </div>
                    </div>

                    @if ($isAdmin)
                        <div class="mt-4 rounded-[28px] border border-slate-200 bg-slate-50/90 px-5 py-4 text-sm leading-6 text-slate-600" data-account-role-section="manager">
                            Менеджер получает доступ к чатам, подтверждению регистраций и работе со своими клиентами. Системные разделы 1С остаются только у администратора.
                        </div>
                    @endif

                    <button type="submit" class="action-button access-user-create__submit mt-6" @if ($isAdmin) data-account-role-submit @endif>
                        {{ $isAdmin ? ($selectedAccountType === 'manager' ? 'Создать менеджера' : 'Создать клиента') : 'Создать клиента' }}
                    </button>
                </form>
            </article>

            <article class="surface-card reveal-card catalog-account-panel">
                <div class="catalog-page-head">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Регистрация</p>
                        <h2 class="mt-2 font-['IBM_Plex_Sans'] text-3xl font-semibold tracking-tight text-slate-950">Заявки на подтверждение</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">
                            Пользователь сам заполняет форму регистрации, а менеджер или администратор только подтверждает доступ и назначает ответственного.
                        </p>
                    </div>
                    <span class="soft-badge soft-badge--dark">{{ $pendingRegistrationRequests->count() }}</span>
                </div>

                <div class="mt-6 space-y-4">
                    @forelse ($pendingRegistrationRequests as $registrationRequest)
                        <article class="rounded-[28px] border border-slate-200 bg-white/92 p-5 shadow-[0_20px_40px_-34px_rgba(15,23,42,0.18)]">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0 space-y-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-lg font-semibold text-slate-950">{{ $registrationRequest->name }}</h3>
                                        <span class="inline-flex min-h-8 items-center rounded-full bg-amber-50 px-3 text-[11px] font-bold uppercase tracking-[0.2em] text-amber-700">Ожидает</span>
                                    </div>

                                    <div class="grid gap-2 text-sm text-slate-600 sm:grid-cols-2">
                                        <p><strong class="text-slate-900">Компания:</strong> {{ $registrationRequest->company ?: 'Не указана' }}</p>
                                        <p><strong class="text-slate-900">Город:</strong> {{ $registrationRequest->city ?: '—' }}</p>
                                        <p><strong class="text-slate-900">Логин:</strong> {{ $registrationRequest->login }}</p>
                                        <p><strong class="text-slate-900">Email:</strong> {{ $registrationRequest->email ?: 'Не указан' }}</p>
                                        <p><strong class="text-slate-900">Телефон:</strong> {{ $registrationRequest->phone ?: 'Не указан' }}</p>
                                    </div>

                                    @if ($registrationRequest->contactPeopleList() !== [])
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($registrationRequest->contactPeopleList() as $contactPerson)
                                                <span class="inline-flex min-h-8 items-center rounded-full border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-600">{{ $contactPerson }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if ($registrationRequest->messengersList() !== [])
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($registrationRequest->messengersList() as $messenger)
                                                <span class="inline-flex min-h-8 items-center rounded-full border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-600">{{ $messenger }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if ($registrationRequest->delivery_address)
                                        <p class="rounded-[22px] border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-700">
                                            <strong class="text-slate-900">Адрес:</strong> {{ $registrationRequest->delivery_address }}
                                        </p>
                                    @endif
                                </div>

                                <div class="w-full max-w-sm space-y-3">
                                    <form action="{{ route('manager.registration-requests.approve', $registrationRequest) }}" method="POST" class="space-y-3">
                                        @csrf

                                        @if ($isAdmin)
                                            <label class="access-field">
                                                <span>Менеджер</span>
                                                <select name="manager_id">
                                                    <option value="">Выберите менеджера</option>
                                                    @foreach ($availableManagers as $managerOption)
                                                        <option value="{{ $managerOption->id }}" @selected((string) old('manager_id') === (string) $managerOption->id)>
                                                            {{ $managerOption->name }}@if ($managerOption->city) — {{ $managerOption->city }}@endif
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </label>
                                        @endif

                                        <label class="access-field">
                                            <span>Город клиента <span class="text-xs font-normal text-amber-700">(прайс-зона)</span></span>
                                            <select name="price_profile_id" required>
                                                <option value="">— Выбери город из списка —</option>
                                                @foreach ($priceProfiles as $profile)
                                                    @php
                                                        $isHinted = $registrationRequest->city
                                                            && mb_strtolower($registrationRequest->city) === mb_strtolower($profile->name);
                                                    @endphp
                                                    <option value="{{ $profile->id }}" @selected($isHinted)>
                                                        {{ $profile->name }}@if ($isHinted) — клиент указал{{ ' этот город' }}@endif
                                                    </option>
                                                @endforeach
                                            </select>
                                            @if ($registrationRequest->city)
                                                <small class="text-xs text-slate-500 mt-1">Клиент написал: «{{ $registrationRequest->city }}»</small>
                                            @endif
                                        </label>

                                        <button type="submit" class="action-button w-full justify-center">Подтвердить регистрацию</button>
                                    </form>

                                    <form action="{{ route('manager.registration-requests.reject', $registrationRequest) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="ghost-button w-full justify-center" onclick="return confirm('Отказать в регистрации?')">Отказать</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="catalog-account-empty">
                            Новых заявок на регистрацию пока нет.
                        </div>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="surface-card reveal-card catalog-account-panel mt-6">
            <div class="catalog-page-head">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">{{ $isAdmin ? 'База клиентов' : 'Мои клиенты' }}</p>
                    <h2 class="mt-2 font-['IBM_Plex_Sans'] text-3xl font-semibold tracking-tight text-slate-950">
                        {{ $isAdmin ? 'Клиенты системы' : 'Клиенты менеджера' }}
                    </h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        Компактный список с раскрытием деталей: контакты, адреса, количество заказов и быстрый переход в чат.
                    </p>
                </div>
                <span class="soft-badge soft-badge--dark">{{ $managedUsers->count() }}</span>
            </div>

            <div class="mt-6 space-y-3">
                @forelse ($managedUsers as $managedUser)
                    <details class="rounded-[28px] border border-slate-200 bg-white/95 shadow-[0_20px_40px_-34px_rgba(15,23,42,0.16)]">
                        <summary class="flex cursor-pointer list-none flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-lg font-semibold text-slate-950">{{ $managedUser->name }}</h3>
                                    <span class="inline-flex min-h-8 items-center rounded-full px-3 text-[11px] font-bold uppercase tracking-[0.2em] {{ $managedUser->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $managedUser->is_active ? 'Активен' : 'Отключен' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">{{ $managedUser->company ?: $managedUser->login }}</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-600">
                                <span class="inline-flex min-h-9 items-center rounded-full border border-slate-200 bg-slate-50 px-4">{{ $managedUser->orders_count }} заказов</span>
                                @if ($isAdmin && $managedUser->manager)
                                    <span class="inline-flex min-h-9 items-center rounded-full border border-slate-200 bg-slate-50 px-4">Менеджер: {{ $managedUser->manager->name }}</span>
                                @endif
                            </div>
                        </summary>

                        <div class="grid gap-5 border-t border-slate-200 px-5 py-5 lg:grid-cols-[minmax(0,1fr)_280px]">
                            <div class="space-y-4">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-[22px] border border-slate-200 bg-slate-50 px-4 py-3">
                                        <span class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500">Логин</span>
                                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $managedUser->login }}</p>
                                    </div>
                                    <div class="rounded-[22px] border border-slate-200 bg-slate-50 px-4 py-3">
                                        <span class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500">Email</span>
                                        <p class="mt-2 break-all text-sm font-semibold text-slate-900">{{ $managedUser->email ?: 'Не указан' }}</p>
                                    </div>
                                    <div class="rounded-[22px] border border-slate-200 bg-slate-50 px-4 py-3">
                                        <span class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500">Телефон</span>
                                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $managedUser->phone ?: 'Не указан' }}</p>
                                    </div>
                                    <div class="rounded-[22px] border border-slate-200 bg-slate-50 px-4 py-3">
                                        <span class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500">Контакты</span>
                                        <p class="mt-2 text-sm font-semibold text-slate-900">
                                            {{ $managedUser->contactPeopleList() !== [] ? implode(', ', $managedUser->contactPeopleList()) : 'Не указаны' }}
                                        </p>
                                    </div>
                                </div>

                                @if ($managedUser->messengersList() !== [])
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($managedUser->messengersList() as $messenger)
                                            <span class="inline-flex min-h-8 items-center rounded-full border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600">{{ $messenger }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="space-y-3">
                                    <h4 class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">Адреса</h4>
                                    @forelse ($managedUser->addresses as $address)
                                        <div class="rounded-[22px] border border-slate-200 bg-white px-4 py-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-900">{{ $address->title }}</p>
                                                    <p class="mt-1 text-sm leading-6 text-slate-600">{{ $address->address }}</p>
                                                </div>
                                                @if ($address->is_default)
                                                    <span class="inline-flex min-h-8 items-center rounded-full bg-emerald-50 px-3 text-[11px] font-bold uppercase tracking-[0.2em] text-emerald-700">Основной</span>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <div class="rounded-[22px] border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                                            Адреса пока не добавлены.
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            <aside class="space-y-3">
                                <div class="rounded-[22px] border border-slate-200 bg-slate-50 px-4 py-3">
                                    <span class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500">Статус доступа</span>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $managedUser->is_active ? 'Доступ открыт' : 'Доступ выключен' }}</p>
                                </div>

                                <div class="rounded-[22px] border border-slate-200 bg-slate-50 px-4 py-3">
                                    <span class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500">Заказов</span>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $managedUser->orders_count }}</p>
                                </div>

                                <a href="{{ route('manager.chats.index', ['client' => $managedUser]) }}" class="ghost-button w-full justify-center">Открыть чат</a>

                                @if ($isAdmin && \Illuminate\Support\Facades\Route::has('admin.users.destroy'))
                                    <form
                                        action="{{ route('admin.users.destroy', $managedUser) }}"
                                        method="POST"
                                        onsubmit="return confirm('Удалить пользователя «{{ addslashes($managedUser->name) }}»?\n\nВсе данные, заказы и переписка будут удалены безвозвратно.')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="catalog-inline-action catalog-inline-action--danger w-full justify-center mt-1">
                                            Удалить пользователя
                                        </button>
                                    </form>
                                @endif
                            </aside>
                        </div>
                    </details>
                @empty
                    <div class="catalog-account-empty">
                        Клиенты пока не добавлены.
                    </div>
                @endforelse
            </div>
        </section>
    @else
        @php
            $profileContactPeople = old('contact_people');
            $profileMessengers = old('messengers');
            $profileContactPeople = is_array($profileContactPeople) && count(array_filter($profileContactPeople, fn ($item) => filled($item))) > 0 ? array_values($profileContactPeople) : (($currentUser->contactPeopleList() !== []) ? $currentUser->contactPeopleList() : ['']);
            $profileMessengers = is_array($profileMessengers) && count(array_filter($profileMessengers, fn ($item) => filled($item))) > 0 ? array_values($profileMessengers) : (($currentUser->messengersList() !== []) ? $currentUser->messengersList() : ['']);
        @endphp

        <section class="catalog-account-grid">
            <article class="surface-card reveal-card catalog-account-hero">
                <div class="space-y-6">
                    <div class="space-y-4">
                        <span class="soft-badge">Профиль</span>
                        <div class="space-y-3">
                            <h1 class="catalog-account-hero__title">Личный кабинет</h1>
                            <p class="catalog-account-hero__text">
                                Проверьте контакты и адреса доставки. Менеджер увидит их при обработке заказов, а переписка вынесена в отдельный виджет на всех страницах.
                            </p>
                        </div>
                    </div>

                    <div class="catalog-account-hero__meta">
                        <div class="catalog-account-meta-card">
                            <span>Компания</span>
                            <strong>{{ $currentUser->company ?: 'Не указана' }}</strong>
                        </div>
                        <div class="catalog-account-meta-card">
                            <span>Логин</span>
                            <strong>{{ $currentUser->login }}</strong>
                        </div>
                        <div class="catalog-account-meta-card">
                            <span>Email</span>
                            <strong>{{ $currentUser->email ?: 'Не указан' }}</strong>
                        </div>
                    </div>
                </div>

                <div class="catalog-account-hero__actions">
                    <a href="{{ route('orders.index') }}" class="action-button">Мои заказы</a>

                    @if ($assignedManager)
                        <button type="button" class="ghost-button" data-support-widget-open>Открыть чат с менеджером</button>
                    @endif
                </div>
            </article>

            <aside class="surface-card reveal-card catalog-account-sidebar">
                <div class="catalog-account-sidebar__stack">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Менеджер</p>
                        <h2 class="mt-2 font-['IBM_Plex_Sans'] text-2xl font-semibold tracking-tight text-slate-950">
                            {{ $assignedManager?->name ?: 'Пока не назначен' }}
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            @if ($assignedManager)
                                Ваш менеджер подтверждает заявки, видит выбранные адреса и отвечает в общем чате.
                            @else
                                Менеджер будет показан здесь после назначения администратором.
                            @endif
                        </p>
                    </div>

                    @if ($assignedManager)
                        <div class="space-y-3">
                            <div class="rounded-[22px] border border-slate-200 bg-white/90 px-4 py-3">
                                <span class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500">Телефон</span>
                                <p class="mt-2 text-sm font-semibold text-slate-900">{{ $assignedManager->phone ?: 'Не указан' }}</p>
                            </div>
                            <div class="rounded-[22px] border border-slate-200 bg-white/90 px-4 py-3">
                                <span class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500">Email</span>
                                <p class="mt-2 break-all text-sm font-semibold text-slate-900">{{ $assignedManager->email ?: 'Не указан' }}</p>
                            </div>
                            @if ($assignedManager->messengersList() !== [])
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($assignedManager->messengersList() as $messenger)
                                        <span class="inline-flex min-h-8 items-center rounded-full border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-600">{{ $messenger }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </aside>
        </section>

        <section class="catalog-account-columns mt-6">
            <article class="surface-card reveal-card catalog-account-panel">
                <div class="catalog-page-head">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Контакты</p>
                        <h2 class="mt-2 font-['IBM_Plex_Sans'] text-3xl font-semibold tracking-tight text-slate-950">Данные профиля</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">
                            Заполните актуальные контакты. Первый контакт и первый мессенджер считаются основными.
                        </p>
                    </div>
                </div>

                <form action="{{ route('account.update') }}" method="POST" class="access-user-create__form mt-6">
                    @csrf
                    @method('PATCH')

                    <div class="access-user-create__grid">
                        <label class="access-field">
                            <span>Имя</span>
                            <input type="text" name="name" value="{{ old('name', $currentUser->name) }}" placeholder="Иван Петров">
                        </label>

                        <label class="access-field">
                            <span>Компания</span>
                            <input type="text" name="company" value="{{ old('company', $currentUser->company) }}" placeholder="ООО МАЖОР">
                        </label>

                        <label class="access-field access-field--full">
                            <span>Телефон</span>
                            <input type="text" name="phone" value="{{ old('phone', $currentUser->phone) }}" placeholder="+7 999 000-00-00">
                        </label>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div class="catalog-repeatable" data-repeatable>
                            <div class="catalog-repeatable__head">
                                <span>Контактные лица</span>
                                <button type="button" class="catalog-inline-action" data-repeatable-add>+ Добавить</button>
                            </div>

                            <div class="catalog-repeatable__items" data-repeatable-items>
                                @foreach ($profileContactPeople as $contactPerson)
                                    <div class="catalog-repeatable__item" data-repeatable-row>
                                        <input type="text" name="contact_people[]" value="{{ $contactPerson }}" placeholder="Контакт по заявкам" class="catalog-clean-input">
                                        <button type="button" class="catalog-inline-action" data-repeatable-remove>Удалить</button>
                                    </div>
                                @endforeach
                            </div>

                            <template data-repeatable-template>
                                <div class="catalog-repeatable__item" data-repeatable-row>
                                    <input type="text" name="contact_people[]" value="" placeholder="Контакт по заявкам" class="catalog-clean-input">
                                    <button type="button" class="catalog-inline-action" data-repeatable-remove>Удалить</button>
                                </div>
                            </template>
                        </div>

                        <div class="catalog-repeatable" data-repeatable>
                            <div class="catalog-repeatable__head">
                                <span>Мессенджеры</span>
                                <button type="button" class="catalog-inline-action" data-repeatable-add>+ Добавить</button>
                            </div>

                            <div class="catalog-repeatable__items" data-repeatable-items>
                                @foreach ($profileMessengers as $messenger)
                                    <div class="catalog-repeatable__item" data-repeatable-row>
                                        <input type="text" name="messengers[]" value="{{ $messenger }}" placeholder="@telegram или WhatsApp" class="catalog-clean-input">
                                        <button type="button" class="catalog-inline-action" data-repeatable-remove>Удалить</button>
                                    </div>
                                @endforeach
                            </div>

                            <template data-repeatable-template>
                                <div class="catalog-repeatable__item" data-repeatable-row>
                                    <input type="text" name="messengers[]" value="" placeholder="@telegram или WhatsApp" class="catalog-clean-input">
                                    <button type="button" class="catalog-inline-action" data-repeatable-remove>Удалить</button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <button type="submit" class="action-button access-user-create__submit mt-6">Сохранить данные</button>
                </form>
            </article>

            {{-- Адреса доставки убраны из ЛК — теперь вводятся прямо в корзине
                 при оформлении заказа (с опцией «сохранить адрес»). --}}
        </section>
    @endif
@endsection
