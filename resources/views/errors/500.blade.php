@extends('layouts.app')

@section('title', 'Что-то пошло не так — МАЖОР')

@section('content')
    <section class="catalog-error-screen" aria-labelledby="error-500-title">
        <div class="catalog-error-screen__inner">
            <div class="catalog-error-screen__mark" aria-hidden="true">
                <span class="catalog-error-screen__digit">5</span>
                <span class="catalog-error-screen__orb"></span>
                <span class="catalog-error-screen__digit">0</span>
                <span class="catalog-error-screen__digit">0</span>
            </div>

            <h1 id="error-500-title" class="catalog-error-screen__title">
                Что-то пошло не так
            </h1>

            <p class="catalog-error-screen__text">
                Наш сервер уже разбирается с ситуацией. Попробуйте обновить страницу или вернуться в каталог через минуту.
            </p>

            <div class="catalog-error-screen__actions">
                <a href="{{ route('catalog.index') }}" class="catalog-buy-button">В каталог</a>
                <a href="{{ url()->current() }}" class="ghost-button">Обновить страницу</a>
            </div>
        </div>
    </section>
@endsection
