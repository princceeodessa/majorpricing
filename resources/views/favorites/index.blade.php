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
        <div class="surface-card mt-6 {{ $favoriteProducts->isNotEmpty() ? 'hidden' : '' }}" data-favorites-empty-state>
            <div class="catalog-empty-state">
                <span class="catalog-empty-state__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 20.2 4.9 13.4a4.5 4.5 0 0 1 6.4-6.3L12 7.8l.7-.7a4.5 4.5 0 1 1 6.4 6.3Z"/></svg>
                </span>
                <h2 class="catalog-empty-state__title">Избранное пока пусто</h2>
                <p class="catalog-empty-state__text">
                    Нажмите на сердечко у нужного товара, и он сразу появится в этом списке.
                </p>
                <a href="{{ route('catalog.index') }}" class="catalog-buy-button catalog-empty-state__cta">Открыть каталог</a>
            </div>
        </div>

        <div class="catalog-grid catalog-grid--dense mt-6 {{ $favoriteProducts->isEmpty() ? 'hidden' : '' }}" data-favorites-grid>
            @foreach ($favoriteProducts as $index => $product)
                @include('catalog.partials.product-card', ['product' => $product, 'delay' => ($index % 8) * 55])
            @endforeach
        </div>
    </div>
@endsection
