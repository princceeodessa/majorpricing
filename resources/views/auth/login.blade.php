@extends('layouts.app')

@section('title', 'Авторизация MAJOR')

@section('content')
    <div class="catalog-auth-grid grid min-h-[calc(100vh-3rem)] items-center gap-8 py-8 lg:grid-cols-[1.05fr_0.95fr]">
        <section class="surface-card hero-panel reveal-card catalog-auth-hero" style="--card-accent: #d11117;">
            <span class="soft-badge">Закрытая авторизация</span>
            <h1 class="mt-6 max-w-3xl font-['IBM_Plex_Sans'] text-4xl font-semibold leading-tight text-slate-950 sm:text-5xl">
                Доступ в систему выдается только менеджером и работает без регистрации.
            </h1>
            <p class="mt-5 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg">
                Пользователь получает логин, пароль и назначенный прайс-профиль вручную. Самостоятельная регистрация на сайте отключена.
            </p>

            <div class="mt-8 grid gap-3 sm:grid-cols-3">
                <div class="stat-card">
                    <span>Доступ</span>
                    <strong>Только по логину</strong>
                </div>
                <div class="stat-card">
                    <span>Управление</span>
                    <strong>Через менеджера</strong>
                </div>
            </div>
        </section>

        <section class="surface-card reveal-card catalog-auth-form p-9 lg:p-11">
            <div class="max-w-lg">
                <span class="soft-badge">Авторизация</span>
                <h2 class="mt-5 font-['IBM_Plex_Sans'] text-[2.1rem] font-semibold text-slate-950 sm:text-[2.35rem]">Вход в кабинет</h2>
                <p class="mt-3 text-base leading-7 text-slate-600">
                    Введите логин или email и пароль. После входа откроется закрытая система доступа.
                </p>

                @if (app()->environment('local'))
                @endif
            </div>

            <form action="{{ route('login.store') }}" method="POST" class="mt-9 space-y-5">
                @csrf

                <div class="space-y-2">
                    <label for="login" class="text-base font-semibold text-slate-800">Логин или email</label>
                    <input
                        id="login"
                        type="text"
                        name="login"
                        value="{{ old('login') }}"
                        placeholder="manager или manager@major.local"
                        class="w-full rounded-[24px] border border-slate-200 bg-white px-5 py-[1.05rem] text-base text-slate-900 outline-none transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100"
                    >
                    @error('login')
                        <p class="text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-2">
                    <label for="password" class="text-base font-semibold text-slate-800">Пароль</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        placeholder="Введите пароль"
                        class="w-full rounded-[24px] border border-slate-200 bg-white px-5 py-[1.05rem] text-base text-slate-900 outline-none transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100"
                    >
                    @error('password')
                        <p class="text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-3 rounded-[24px] border border-slate-200 bg-slate-50/80 px-4 py-4 text-base text-slate-600">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-orange-500 focus:ring-orange-400">
                    Запомнить вход на этом устройстве
                </label>

                <button type="submit" class="action-button w-full">Войти</button>
            </form>

            @if (app()->environment('local'))
            @endif
        </section>
    </div>
@endsection
