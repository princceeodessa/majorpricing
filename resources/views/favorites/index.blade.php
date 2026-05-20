@extends('layouts.app')

@section('title', 'Избранное - МАЖОР')

@section('content')
    <section class="surface-card reveal-card p-6 sm:p-8">
        <div class="catalog-page-head">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Избранное</p>
                <h1 class="mt-2 font-['IBM_Plex_Sans'] text-4xl font-semibold tracking-tight text-slate-950">Личный список товаров</h1>
                <p class="mt-4 max-w-3xl text-base leading-7 text-slate-600">
                    Отмечайте позиции сердечком и возвращайтесь к ним в любой момент. Это быстрый shortlist клиента внутри закрытого каталога.
                </p>
            </div>

            <div class="catalog-list-header__stats">
                <div class="catalog-stat-box">
                    <span>В избранном</span>
                    <strong data-favorites-count>{{ $favoritesCount }}</strong>
                </div>
                <div class="catalog-stat-box">
                    <span>Корзина</span>
                    <strong>{{ $headerCartCount ?? 0 }}</strong>
                </div>
                <div class="catalog-stat-box">
                    <span>Заказы</span>
                    <strong>{{ $headerOrdersCount ?? 0 }}</strong>
                </div>
            </div>
        </div>
    </section>

    <div data-favorites-page>
        <div class="surface-card mt-6 p-12 text-center {{ $favoriteProducts->isNotEmpty() ? 'hidden' : '' }}" data-favorites-empty-state>
            <h2 class="font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Избранное пока пусто</h2>
            <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-slate-600">
                Нажмите на сердечко у нужного товара, и он сразу появится в этом списке.
            </p>
            <a href="{{ route('catalog.index') }}" class="catalog-buy-button mx-auto mt-6 w-fit">Открыть каталог</a>
        </div>

        <div class="catalog-grid catalog-grid--dense mt-6 {{ $favoriteProducts->isEmpty() ? 'hidden' : '' }}" data-favorites-grid>
            @foreach ($favoriteProducts as $index => $product)
                @include('catalog.partials.product-card', ['product' => $product, 'delay' => ($index % 8) * 55])
            @endforeach
        </div>
    </div>
@endsection
