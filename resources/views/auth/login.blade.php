@extends('layouts.app')

@section('title', 'Вход в каталог')

@section('content')
    <div class="catalog-auth-grid grid min-h-[calc(100vh-3rem)] items-center gap-8 py-8 lg:grid-cols-[1.05fr_0.95fr]">
        <section class="surface-card hero-panel reveal-card catalog-auth-hero" style="--card-accent: #d11117;">
            <span class="soft-badge">Закрытый B2B-каталог</span>
            <h1 class="mt-6 max-w-3xl font-['IBM_Plex_Sans'] text-4xl font-semibold leading-tight text-slate-950 sm:text-5xl">
                Каталог товаров с персональными ценами и доступом только по авторизации.
            </h1>
            <p class="mt-5 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg">
                Сайт работает только для выданных пользователей. Регистрация отключена: каждому клиенту или менеджеру назначается свой доступ и своя ценовая колонка.
            </p>

            <div class="mt-8 grid gap-3 sm:grid-cols-3">
                <div class="stat-card">
                    <span>Формат</span>
                    <strong>Private only</strong>
                </div>
                <div class="stat-card">
                    <span>Прайс</span>
                    <strong>По профилю</strong>
                </div>
                <div class="stat-card">
                    <span>Каталог</span>
                    <strong>Готов к работе</strong>
                </div>
            </div>
        </section>

        <section class="surface-card reveal-card catalog-auth-form p-9 lg:p-11">
            <div class="max-w-lg">
                <span class="soft-badge">Авторизация</span>
                <h2 class="mt-5 font-['IBM_Plex_Sans'] text-[2.1rem] font-semibold text-slate-950 sm:text-[2.35rem]">Вход в кабинет</h2>
                <p class="mt-3 text-base leading-7 text-slate-600">
                    Введите логин или email и пароль. После входа откроется закрытая витрина с товарами и персональной ценой.
                </p>

                @if (app()->environment('local'))
                    <div class="mt-5 rounded-[24px] border border-slate-200 bg-slate-50/90 p-4 text-sm leading-6 text-slate-700">
                        Для входа используйте логин <span class="font-semibold text-slate-950">manager</span>, <span class="font-semibold text-slate-950">partner</span> или <span class="font-semibold text-slate-950">vip</span>.
                        Пароль для всех демо-пользователей: <span class="font-semibold text-slate-950">MajorDemo123!</span>
                    </div>
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

                <button type="submit" class="action-button w-full">Открыть каталог</button>
            </form>

            @if (app()->environment('local'))
                <div class="mt-9 rounded-[30px] border border-slate-200 bg-slate-50/90 p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Демо-доступ</p>
                    <div class="mt-4 space-y-2 text-base text-slate-700">
                        <p><span class="font-semibold text-slate-950">manager</span> / <span class="font-semibold text-slate-950">MajorDemo123!</span></p>
                        <p><span class="font-semibold text-slate-950">partner</span> / <span class="font-semibold text-slate-950">MajorDemo123!</span></p>
                        <p><span class="font-semibold text-slate-950">vip</span> / <span class="font-semibold text-slate-950">MajorDemo123!</span></p>
                    </div>
                </div>
            @endif
        </section>
    </div>
@endsection
