@extends('layouts.app')

@section('title', 'Кабинет MAJOR')

@section('content')
    @php($user = auth()->user())

    @if ($user->isManager())
        <section class="access-dashboard-grid">
            <div class="surface-card access-dashboard-hero">
                <span class="soft-badge">Менеджерский кабинет</span>
                <h1 class="access-dashboard-hero__title">Клиенты, контакты и работа с заказами в одном месте.</h1>
                <p class="access-dashboard-hero__text">
                    Здесь менеджер создает доступы, видит контактные данные клиентов и забирает заказы в работу. Все, что клиент укажет в профиле и в адресах доставки, сразу появится в менеджерском кабинете и в карточке заказа.
                </p>

                <div class="access-dashboard-stats">
                    <div class="stat-card">
                        <span>Пользователей</span>
                        <strong>{{ $managementStats['totalUsers'] }}</strong>
                    </div>
                    <div class="stat-card">
                        <span>Активных</span>
                        <strong>{{ $managementStats['activeUsers'] }}</strong>
                    </div>
                    <div class="stat-card">
                        <span>Новых заказов</span>
                        <strong>{{ $managementStats['newOrders'] }}</strong>
                    </div>
                    <div class="stat-card">
                        <span>В работе</span>
                        <strong>{{ $managementStats['processingOrders'] }}</strong>
                    </div>
                </div>

                <div class="catalog-account-hero__actions">
                    <a href="{{ route('orders.index') }}" class="action-button">Открыть заказы клиентов</a>
                    <a href="{{ route('manager.onec.show') }}" class="ghost-button">Диагностика 1С</a>
                </div>
            </div>

            <div class="surface-card access-user-create">
                <div>
                    <span class="soft-badge">Новый доступ</span>
                    <h2 class="access-user-create__title">Добавление пользователя</h2>
                    <p class="access-user-create__text">
                        Создайте логин клиента и при необходимости сразу внесите контакты и его первый адрес.
                    </p>
                </div>

                <form action="{{ route('manager.users.store') }}" method="POST" class="access-user-create__form">
                    @csrf

                    <div class="access-user-create__grid">
                        <label class="access-field">
                            <span>Имя</span>
                            <input type="text" name="name" value="{{ old('name') }}" placeholder="Иван Петров">
                        </label>

                        <label class="access-field">
                            <span>Компания</span>
                            <input type="text" name="company" value="{{ old('company') }}" placeholder="ООО Партнер">
                        </label>

                        <label class="access-field">
                            <span>Контактное лицо</span>
                            <input type="text" name="contact_person" value="{{ old('contact_person') }}" placeholder="Менеджер закупки">
                        </label>

                        <label class="access-field">
                            <span>Телефон</span>
                            <input type="text" name="phone" value="{{ old('phone') }}" placeholder="+7 999 000-00-00">
                        </label>

                        <label class="access-field">
                            <span>Telegram</span>
                            <input type="text" name="telegram" value="{{ old('telegram') }}" placeholder="@major_client">
                        </label>

                        <label class="access-field">
                            <span>Логин</span>
                            <input type="text" name="login" value="{{ old('login') }}" placeholder="client_01">
                        </label>

                        <label class="access-field">
                            <span>Email</span>
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

                        <label class="access-field access-field--full">
                            <span>Первичный адрес</span>
                            <input type="text" name="delivery_address" value="{{ old('delivery_address') }}" placeholder="Город, улица, объект, удобное время связи">
                        </label>
                    </div>

                    <label class="access-checkbox">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') === '1')>
                        Пользователь активен сразу после создания
                    </label>

                    <button type="submit" class="action-button access-user-create__submit">Добавить пользователя</button>
                </form>
            </div>
        </section>

        <section class="mt-10">
            <div class="catalog-section-head">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Клиенты</p>
                    <h2 class="mt-2 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Все пользователи</h2>
                </div>
                <p class="max-w-xl text-sm leading-6 text-slate-600">
                    Список клиентов с текущими контактами и адресами доставки.
                </p>
            </div>

            <div class="access-users-grid mt-5">
                @foreach ($managedUsers as $managedUser)
                    <article class="surface-card access-user-card">
                        <div class="access-user-card__head">
                            <div>
                                <span class="soft-badge">{{ $managedUser->is_active ? 'Активен' : 'Отключен' }}</span>
                                <h3>{{ $managedUser->name }}</h3>
                            </div>
                            @if ($managedUser->is_manager)
                                <strong class="access-user-card__role">Менеджер</strong>
                            @endif
                        </div>

                        <div class="access-user-card__meta">
                            <div>
                                <span>Компания</span>
                                <strong>{{ $managedUser->company ?: 'Не указана' }}</strong>
                            </div>
                            <div>
                                <span>Логин</span>
                                <strong>{{ $managedUser->login }}</strong>
                            </div>
                            <div>
                                <span>Email</span>
                                <strong>{{ $managedUser->email }}</strong>
                            </div>
                            <div>
                                <span>Телефон</span>
                                <strong>{{ $managedUser->phone ?: 'Не указан' }}</strong>
                            </div>
                            <div class="access-field--full">
                                <span>Контактные лица</span>
                                <strong>
                                    @php($managedContacts = $managedUser->contactPeopleList())
                                    {{ $managedContacts !== [] ? implode(' • ', $managedContacts) : 'Не указаны' }}
                                </strong>
                            </div>
                            <div class="access-field--full">
                                <span>Мессенджеры</span>
                                <strong>
                                    @php($managedMessengers = $managedUser->messengersList())
                                    {{ $managedMessengers !== [] ? implode(' • ', $managedMessengers) : 'Не указаны' }}
                                </strong>
                            </div>
                            <div class="access-field--full">
                                <span>Адреса</span>
                                <strong>
                                    @if ($managedUser->addresses->isNotEmpty())
                                        {{ $managedUser->addresses->map(fn ($address) => $address->formattedLabel())->implode(' • ') }}
                                    @else
                                        Не указаны
                                    @endif
                                </strong>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @else
        @php($contactPeople = old('contact_people', $user->contactPeopleList()))
        @php($messengers = old('messengers', $user->messengersList()))
        @php($contactPeople = is_array($contactPeople) && count(array_filter($contactPeople, fn ($item) => filled($item))) > 0 ? array_values($contactPeople) : [''])
        @php($messengers = is_array($messengers) && count(array_filter($messengers, fn ($item) => filled($item))) > 0 ? array_values($messengers) : [''])

        <section class="surface-card catalog-account-panel">
            <div class="catalog-page-head">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Контакты для менеджера</p>
                    <h2 class="mt-2 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Профиль клиента</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
                        Эти данные менеджер увидит в списке пользователей и в карточке заказа.
                    </p>
                </div>

                <a href="{{ route('orders.index') }}" class="ghost-button">История заказов</a>
            </div>

            <form action="{{ route('account.update') }}" method="POST" class="access-user-create__form mt-8">
                @csrf
                @method('PATCH')

                <div class="access-user-create__grid">
                    <label class="access-field">
                        <span>Имя</span>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" placeholder="Ваше имя">
                    </label>

                    <label class="access-field">
                        <span>Компания</span>
                        <input type="text" name="company" value="{{ old('company', $user->company) }}" placeholder="Название компании">
                    </label>

                    <label class="access-field">
                        <span>Телефон</span>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="+7 999 000-00-00">
                    </label>

                    <div class="access-field access-field--full catalog-repeatable" data-repeatable>
                        <div class="catalog-repeatable__head">
                            <span>Контактные лица</span>
                            <button type="button" class="catalog-inline-action" data-repeatable-add>+ Добавить</button>
                        </div>

                        <div class="catalog-repeatable__items" data-repeatable-items>
                            @foreach ($contactPeople as $contactPerson)
                                <div class="catalog-repeatable__item" data-repeatable-row>
                                    <input
                                        type="text"
                                        name="contact_people[]"
                                        value="{{ $contactPerson }}"
                                        placeholder="К кому обращаться по заказу"
                                        class="catalog-clean-input"
                                    >
                                    <button type="button" class="catalog-inline-action" data-repeatable-remove>Удалить</button>
                                </div>
                            @endforeach
                        </div>

                        <template data-repeatable-template>
                            <div class="catalog-repeatable__item" data-repeatable-row>
                                <input type="text" name="contact_people[]" value="" placeholder="К кому обращаться по заказу" class="catalog-clean-input">
                                <button type="button" class="catalog-inline-action" data-repeatable-remove>Удалить</button>
                            </div>
                        </template>

                        @if ($errors->has('contact_people') || $errors->has('contact_people.*'))
                            <p class="text-sm font-medium text-red-600">{{ $errors->first('contact_people') ?: $errors->first('contact_people.*') }}</p>
                        @endif
                    </div>

                    <div class="access-field access-field--full catalog-repeatable" data-repeatable>
                        <div class="catalog-repeatable__head">
                            <span>Мессенджеры</span>
                            <button type="button" class="catalog-inline-action" data-repeatable-add>+ Добавить</button>
                        </div>

                        <div class="catalog-repeatable__items" data-repeatable-items>
                            @foreach ($messengers as $messenger)
                                <div class="catalog-repeatable__item" data-repeatable-row>
                                    <input
                                        type="text"
                                        name="messengers[]"
                                        value="{{ $messenger }}"
                                        placeholder="@major_client или WhatsApp"
                                        class="catalog-clean-input"
                                    >
                                    <button type="button" class="catalog-inline-action" data-repeatable-remove>Удалить</button>
                                </div>
                            @endforeach
                        </div>

                        <template data-repeatable-template>
                            <div class="catalog-repeatable__item" data-repeatable-row>
                                <input type="text" name="messengers[]" value="" placeholder="@major_client или WhatsApp" class="catalog-clean-input">
                                <button type="button" class="catalog-inline-action" data-repeatable-remove>Удалить</button>
                            </div>
                        </template>

                        @if ($errors->has('messengers') || $errors->has('messengers.*'))
                            <p class="text-sm font-medium text-red-600">{{ $errors->first('messengers') ?: $errors->first('messengers.*') }}</p>
                        @endif
                    </div>
                </div>

                <button type="submit" class="action-button access-user-create__submit">Сохранить профиль</button>
            </form>
        </section>

        <section class="surface-card mt-8 catalog-account-panel">
            <div class="catalog-page-head">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Адреса</p>
                    <h2 class="mt-2 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Адреса для заявок</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
                        В корзине вы будете выбирать один из этих адресов. Менеджер увидит выбранный адрес прямо в заказе.
                    </p>
                </div>
            </div>

            <div class="mt-8 access-dashboard-grid">
                <div class="surface-card access-user-create">
                    <div>
                        <span class="soft-badge">Новый адрес</span>
                        <h3 class="access-user-create__title">Добавить адрес</h3>
                    </div>

                    <form action="{{ route('account.addresses.store') }}" method="POST" class="access-user-create__form">
                        @csrf

                        <div class="access-user-create__grid">
                            <label class="access-field">
                                <span>Название</span>
                                <input type="text" name="title" value="{{ old('title') }}" placeholder="Склад, офис, объект">
                            </label>

                            <label class="access-checkbox self-end">
                                <input type="checkbox" name="is_default" value="1" @checked(old('is_default') === '1' || $userAddresses->isEmpty())>
                                Сделать основным
                            </label>

                            <label class="access-field access-field--full">
                                <span>Адрес</span>
                                <textarea name="address" placeholder="Город, улица, корпус, комментарий для доставки">{{ old('address') }}</textarea>
                            </label>
                        </div>

                        <button type="submit" class="action-button access-user-create__submit">Добавить адрес</button>
                    </form>
                </div>

                <div class="surface-card access-user-create">
                    <div>
                        <span class="soft-badge">Мои адреса</span>
                        <h3 class="access-user-create__title">Сохраненные адреса</h3>
                    </div>

                    @if ($userAddresses->isEmpty())
                        <p class="access-user-create__text">
                            Пока адресов нет. Добавьте первый адрес, и он станет доступен при оформлении заявки.
                        </p>
                    @else
                        <div class="access-users-grid">
                            @foreach ($userAddresses as $address)
                                <article class="surface-card access-user-card">
                                    <div class="access-user-card__head">
                                        <div>
                                            <span class="soft-badge">{{ $address->is_default ? 'Основной адрес' : 'Адрес' }}</span>
                                            <h3>{{ $address->title }}</h3>
                                        </div>
                                    </div>

                                    <div class="access-user-card__meta">
                                        <div class="access-field--full">
                                            <span>Адрес доставки</span>
                                            <strong>{{ $address->address }}</strong>
                                        </div>
                                    </div>

                                    <div class="catalog-account-address-card__actions">
                                        @unless ($address->is_default)
                                            <form action="{{ route('account.addresses.default', $address) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="catalog-reset-button">Сделать основным</button>
                                            </form>
                                        @endunless

                                        <form action="{{ route('account.addresses.destroy', $address) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="catalog-inline-action">Удалить</button>
                                        </form>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif
@endsection
