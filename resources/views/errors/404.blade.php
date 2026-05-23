@extends('layouts.app')

@section('title', 'Страница не найдена — МАЖОР')
@section('meta_description', 'Похоже, такой страницы у нас нет. Вернитесь в каталог или на главную.')

@section('content')
    <section class="catalog-error-screen" aria-labelledby="error-404-title">
        <div class="catalog-error-screen__inner">
            <div class="catalog-error-screen__mark" aria-hidden="true">
                <span class="catalog-error-screen__digit">4</span>
                <span class="catalog-error-screen__orb"></span>
                <span class="catalog-error-screen__digit">4</span>
            </div>

            <h1 id="error-404-title" class="catalog-error-screen__title">
                Страница потерялась
            </h1>

            <p class="catalog-error-screen__text">
                Кажется, такого адреса в каталоге нет. Возможно, ссылка устарела или товар временно скрыт менеджером.
                Вернитесь в каталог или попробуйте поиск.
            </p>

            <div class="catalog-error-screen__actions">
                <a href="{{ route('catalog.index') }}" class="catalog-buy-button">Открыть каталог</a>
                <a href="{{ url('/') }}" class="ghost-button">На главную</a>
            </div>
        </div>
    </section>
@endsection
