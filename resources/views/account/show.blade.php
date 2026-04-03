@extends('layouts.app')

@section('title', 'Авторизация MAJOR')

@section('content')
    @php($user = auth()->user())

    @if ($user->isManager())
        <section class="access-dashboard-grid">
            <div class="surface-card access-dashboard-hero">
                <span class="soft-badge">Менеджерский доступ</span>
                <h1 class="access-dashboard-hero__title">Управление пользователями и выдачей доступа.</h1>
                <p class="access-dashboard-hero__text">
                    Система работает как закрытая авторизация без регистрации. Менеджер вручную создает логины, назначает прайс-профиль и управляет активностью пользователей.
                </p>

                <div class="access-dashboard-stats">
                    <div class="stat-card">
                        <span>Всего пользователей</span>
                        <strong>{{ $managementStats['totalUsers'] }}</strong>
                    </div>
                    <div class="stat-card">
                        <span>Активных</span>
                        <strong>{{ $managementStats['activeUsers'] }}</strong>
                    </div>
                    <div class="stat-card">
                        <span>Отключено</span>
                        <strong>{{ $managementStats['disabledUsers'] }}</strong>
                    </div>
                    <div class="stat-card">
                        <span>Прайс-профилей</span>
                        <strong>{{ $managementStats['profilesCount'] }}</strong>
                    </div>
                </div>
            </div>

            <div class="surface-card access-user-create">
                <div>
                    <span class="soft-badge">Новый пользователь</span>
                    <h2 class="access-user-create__title">Добавление доступа</h2>
                    <p class="access-user-create__text">
                        Создайте логин, пароль и назначьте прайс-профиль. Регистрация для клиента по-прежнему отключена.
                    </p>
                </div>

                <form action="{{ route('manager.users.store') }}" method="POST" class="access-user-create__form">
                    @csrf

                    <div class="access-user-create__grid">
                        <label class="access-field">
                            <span>Имя</span>
                            <input type="text" name="name" value="{{ old('name') }}" placeholder="Например: Иван Петров">
                        </label>

                        <label class="access-field">
                            <span>Компания</span>
                            <input type="text" name="company" value="{{ old('company') }}" placeholder="Например: ООО Партнер">
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
                    <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Доступы</p>
                    <h2 class="mt-2 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Все пользователи</h2>
                </div>
                <p class="max-w-xl text-sm leading-6 text-slate-600">
                    Список созданных пользователей с логином, компанией, статусом и назначенным прайс-профилем.
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
                                <span>Прайс-профиль</span>
                                <strong>{{ $managedUser->priceProfile?->name ?? 'Не назначен' }}</strong>
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
                Для вашего аккаунта включен вход в закрытую систему. Регистрация отключена, а все доступы создаются менеджером вручную.
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

            <div class="access-only-panel__notice">
                Если нужно изменить данные, восстановить пароль или назначить другой прайс-профиль, это делает менеджер со своей панели.
            </div>
        </section>
    @endif
@endsection
