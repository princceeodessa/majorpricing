@extends('layouts.app')

@section('title', 'МАЖОР — вход в личный кабинет')
@section('meta_description', 'МАЖОР — личный кабинет для клиентов и менеджеров. Вход в систему, заявка на регистрацию и доступ к каталогу после подтверждения менеджером.')

@section('content')
    <div class="catalog-auth-screen">
        <section class="surface-card reveal-card catalog-auth-panel">
            <div class="catalog-auth-panel__inner">
                <img
                    src="{{ asset('brand/major-logo-wide.svg') }}"
                    alt="МАЖОР"
                    class="mx-auto mb-6 h-auto w-full max-w-[320px]"
                >

                <h1 class="catalog-auth-panel__title">Вход в личный кабинет</h1>

                <p class="mx-auto mb-8 max-w-[32rem] text-center text-sm leading-6 text-slate-500 sm:text-base">
                    Каталог, корзина и заказы доступны после входа. Если доступа еще нет, оставьте заявку на регистрацию —
                    менеджер проверит данные и откроет доступ.
                </p>

                <form action="{{ route('login.store') }}" method="POST" class="catalog-auth-panel__form">
                    @csrf

                    <div class="space-y-2">
                        <label for="login" class="text-base font-semibold text-slate-800">Логин или email</label>
                        <input
                            id="login"
                            type="text"
                            name="login"
                            value="{{ old('login') }}"
                            placeholder="manager или ваш email"
                            class="w-full rounded-[24px] border border-slate-200 bg-white px-5 py-[1.05rem] text-base text-slate-900 outline-none transition focus:border-sky-500 focus:ring-4 focus:ring-sky-100"
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
                            class="w-full rounded-[24px] border border-slate-200 bg-white px-5 py-[1.05rem] text-base text-slate-900 outline-none transition focus:border-sky-500 focus:ring-4 focus:ring-sky-100"
                        >
                        @error('password')
                            <p class="text-sm font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="action-button w-full">Войти</button>
                </form>

                <div class="mt-6 flex flex-col gap-3 text-center">
                    <a href="{{ route('registration-requests.create') }}" class="ghost-button mx-auto">
                        Подать заявку на регистрацию
                    </a>
                </div>
            </div>
        </section>
    </div>
@endsection
