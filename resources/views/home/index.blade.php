@extends('layouts.app')

@section('title', 'ПОТОЛКОВЫЧ - комплектующие для натяжных потолков')
@section('meta_description', 'ПОТОЛКОВЫЧ - поставщик комплектующих для натяжных потолков: профили, карнизы, решетки, диффузоры, светодиодная лента и расходные материалы. Доступ к каталогу после подтверждения менеджером.')

@section('content')
    <div class="space-y-6 md:space-y-8">
        <section class="surface-card reveal-card overflow-hidden p-6 sm:p-8 lg:p-12">
            <div class="grid gap-8 lg:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)] lg:items-center">
                <div class="space-y-6">
                    <img
                        src="{{ asset('brand/potolkovych-logo-wide.svg') }}"
                        alt="ПОТОЛКОВЫЧ"
                        class="h-auto w-full max-w-[320px]"
                    >

                    <div class="space-y-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.38em] text-slate-500">
                            Комплектующие для натяжных потолков
                        </p>

                        <h1 class="max-w-3xl text-4xl font-extrabold leading-tight text-slate-950 sm:text-5xl">
                            ПОТОЛКОВЫЧ
                        </h1>

                        <p class="max-w-3xl text-base leading-8 text-slate-600 sm:text-lg">
                            Платформа для клиентов и менеджеров с единым каталогом, актуальным ассортиментом,
                            оформлением заказов и подтверждением доступа через менеджера.
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                        <a href="{{ route('login') }}" class="action-button text-center">
                            Войти в личный кабинет
                        </a>
                        <a href="{{ route('registration-requests.create') }}" class="ghost-button text-center">
                            Подать заявку на регистрацию
                        </a>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                    <article class="rounded-[28px] border border-slate-200 bg-white/90 p-5 shadow-[0_20px_50px_rgba(15,23,42,0.06)]">
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Категории</p>
                        <p class="mt-4 text-3xl font-extrabold text-slate-950">{{ $categoryCount }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-500">Основные направления каталога с актуальной структурой.</p>
                    </article>

                    <article class="rounded-[28px] border border-slate-200 bg-white/90 p-5 shadow-[0_20px_50px_rgba(15,23,42,0.06)]">
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Товары</p>
                        <p class="mt-4 text-3xl font-extrabold text-slate-950">{{ number_format($productCount, 0, ',', ' ') }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-500">Ассортимент обновляется из 1С и доступен после подтверждения доступа.</p>
                    </article>

                    <article class="rounded-[28px] border border-slate-200 bg-white/90 p-5 shadow-[0_20px_50px_rgba(15,23,42,0.06)] sm:col-span-2 lg:col-span-1 xl:col-span-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Как это работает</p>
                        <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                            <li>1. Оставляете заявку на регистрацию.</li>
                            <li>2. Менеджер подтверждает доступ и закрепляет клиента.</li>
                            <li>3. После входа доступны каталог, корзина, заказы и связь с менеджером.</li>
                        </ul>
                    </article>
                </div>
            </div>
        </section>

        @if ($featuredCategories->isNotEmpty())
            <section class="surface-card reveal-card p-6 sm:p-8">
                <div class="mb-6 flex items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.34em] text-slate-400">Навигация</p>
                        <h2 class="mt-3 text-3xl font-bold text-slate-950">Основные направления</h2>
                    </div>
                    <a href="{{ route('login') }}" class="hidden text-sm font-semibold text-sky-900 sm:inline-flex">
                        Войти для просмотра каталога
                    </a>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($featuredCategories as $category)
                        <div class="rounded-[24px] border border-slate-200 bg-white/90 px-5 py-4 text-base font-semibold text-slate-800 shadow-[0_15px_35px_rgba(15,23,42,0.05)]">
                            {{ $category->name }}
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
