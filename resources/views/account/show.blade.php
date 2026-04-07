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
                    Здесь менеджер создает доступы, видит контактные данные клиентов и забирает заказы в работу. Все, что клиент укажет в профиле или при оформлении корзины, появится в менеджерском кабинете и в карточке заказа.
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
                        Создайте логин, назначьте прайс-профиль и при необходимости сразу внесите контакты клиента, чтобы менеджеру не пришлось уточнять их позже.
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
                            <input type="text" name="login" value="{{ old('login') }}" placeholder="partner_01">
                        </label>

                        <label class="access-field">
                            <span>Email</span>
                            <input type="email" name="email" value="{{ old('email') }}" placeholder="partner@example.com">
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
                            <span>Адрес / комментарий к доставке</span>
                            <input type="text" name="delivery_address" value="{{ old('delivery_address') }}" placeholder="Город, улица, объект, удобное время связи">
                        </label>

                        <label class="access-field access-field--full">
                            <span>Прайс-профиль</span>
                            <select name="price_profile_id">
                                <option value="">По умолчанию</option>
                                @foreach ($priceProfiles as $priceProfile)
                                    <option value="{{ $priceProfile->id }}" @selected((string) old('price_profile_id') === (string) $priceProfile->id)>
                                        {{ $priceProfile->name }} · {{ $priceProfile->price_label }}
                                    </option>
                                @endforeach
                            </select>
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
                    Список клиентов с текущими контактами и назначенными профилями. Эти данные менеджер увидит и в заказах.
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
                            <div>
                                <span>Контакт</span>
                                <strong>{{ $managedUser->contact_person ?: 'Не указан' }}</strong>
                            </div>
                            <div>
                                <span>Telegram</span>
                                <strong>{{ $managedUser->telegram ?: 'Не указан' }}</strong>
                            </div>
                            <div class="access-field--full">
                                <span>Прайс-профиль</span>
                                <strong>{{ $managedUser->priceProfile?->name ?? 'Не назначен' }}</strong>
                            </div>
                            <div class="access-field--full">
                                <span>Адрес / доставка</span>
                                <strong>{{ $managedUser->delivery_address ?: 'Не указан' }}</strong>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @else
        <section class="surface-card access-only-panel">
            <span class="soft-badge">Авторизация</span>
            <h1 class="access-only-panel__title">Доступ подтвержден.</h1>
            <p class="access-only-panel__text">
                Профиль закрыт для самостоятельной регистрации. Ниже можно заполнить контакты и адрес, чтобы менеджер видел их в кабинете и автоматически получал при оформлении заказа.
            </p>

            <div class="access-only-panel__grid">
                <div class="stat-card">
                    <span>Компания</span>
                    <strong>{{ $user->company ?: 'Не указана' }}</strong>
                </div>
                <div class="stat-card">
                    <span>Логин</span>
                    <strong>{{ $user->login }}</strong>
                </div>
                <div class="stat-card">
                    <span>Email</span>
                    <strong>{{ $user->email }}</strong>
                </div>
                <div class="stat-card">
                    <span>Прайс-профиль</span>
                    <strong>{{ $profile?->name ?? 'Не назначен' }}</strong>
                </div>
            </div>
        </section>

        <section class="surface-card mt-8 catalog-account-panel">
            <div class="catalog-page-head">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Контакты для менеджера</p>
                    <h2 class="mt-2 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Профиль клиента</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
                        Эти данные автоматически подставятся в корзине при оформлении заказа. Их же увидит менеджер в списке пользователей и в карточке заказа.
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
                        <span>Контактное лицо</span>
                        <input type="text" name="contact_person" value="{{ old('contact_person', $user->contact_person) }}" placeholder="К кому обращаться по заказу">
                    </label>

                    <label class="access-field">
                        <span>Телефон</span>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="+7 999 000-00-00">
                    </label>

                    <label class="access-field access-field--full">
                        <span>Telegram / мессенджер</span>
                        <input type="text" name="telegram" value="{{ old('telegram', $user->telegram) }}" placeholder="@major_client">
                    </label>

                    <label class="access-field access-field--full">
                        <span>Адрес / комментарий для доставки</span>
                        <input type="text" name="delivery_address" value="{{ old('delivery_address', $user->delivery_address) }}" placeholder="Город, адрес, объект, удобное время связи">
                    </label>
                </div>

                <button type="submit" class="action-button access-user-create__submit">Сохранить профиль</button>
            </form>
        </section>
    @endif
@endsection
