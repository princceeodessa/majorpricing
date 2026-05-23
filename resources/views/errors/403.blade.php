@extends('layouts.app')

@section('title', 'Доступ ограничен — МАЖОР')

@section('content')
    <section class="catalog-error-screen" aria-labelledby="error-403-title">
        <div class="catalog-error-screen__inner">
            <div class="catalog-error-screen__mark" aria-hidden="true">
                <span class="catalog-error-screen__digit">4</span>
                <span class="catalog-error-screen__orb"></span>
                <span class="catalog-error-screen__digit">0</span>
                <span class="catalog-error-screen__digit">3</span>
            </div>

            <h1 id="error-403-title" class="catalog-error-screen__title">
                Доступ закрыт
            </h1>

            <p class="catalog-error-screen__text">
                Этот раздел доступен только авторизованным пользователям или менеджерам.
                Войдите в личный кабинет или вернитесь в общедоступную часть каталога.
            </p>

            <div class="catalog-error-screen__actions">
                @auth
                    <a href="{{ route('catalog.index') }}" class="catalog-buy-button">В каталог</a>
                @else
                    <a href="{{ route('login') }}" class="catalog-buy-button">Войти</a>
                    <a href="{{ route('catalog.index') }}" class="ghost-button">В каталог</a>
                @endauth
            </div>
        </div>
    </section>
@endsection
