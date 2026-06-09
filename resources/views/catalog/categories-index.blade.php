@extends('layouts.app')

@section('title', 'Категории · МАЖОР')
@section('meta_description', 'Каталог категорий МАЖОР — оптовые поставки строительных и отделочных материалов с актуальными ценами для бизнес-клиентов.')

@php
    use Illuminate\Support\Str;

    $pluralize = function (int $n, array $forms): string {
        $mod10 = $n % 10;
        $mod100 = $n % 100;
        if ($mod10 === 1 && $mod100 !== 11) return $forms[0];
        if (in_array($mod10, [2, 3, 4], true) && ! in_array($mod100, [12, 13, 14], true)) return $forms[1];
        return $forms[2];
    };

    $accentPalette = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#14b8a6', '#0ea5e9', '#6366f1', '#a855f7', '#ec4899'];
@endphp

@section('content')
<div class="categories-page">
    <header class="categories-page__head">
        <h1 class="categories-page__title">Категории</h1>
        <label class="categories-page__search">
            <svg viewBox="0 0 24 24" class="categories-page__search-icon" aria-hidden="true">
                <circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/>
                <path d="M16.5 16.5L21 21" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <input
                type="search"
                placeholder="Поиск по категориям"
                class="categories-page__search-input"
                autocomplete="off"
                data-categories-search
                aria-label="Поиск по категориям"
            >
        </label>
    </header>

    @auth
        <a href="{{ route('orders.index') }}" class="categories-page__featured">
            <span class="categories-page__featured-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M3 12a9 9 0 1 0 3-6.7M3 4v5h5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <span class="categories-page__featured-body">
                <strong>Уже заказывали</strong>
                <span>Быстро повторить покупки из прошлых заказов</span>
            </span>
            <span class="categories-page__featured-arrow" aria-hidden="true">›</span>
        </a>
    @endauth

    <ul class="categories-grid" data-categories-list>
        @forelse ($categories as $index => $category)
            @php
                $accent = $category->accent_color && preg_match('/^#[0-9a-fA-F]{3,8}$/', $category->accent_color)
                    ? $category->accent_color
                    : $accentPalette[$index % count($accentPalette)];
                $thumb = $thumbnails[$category->id] ?? null;
                $initials = Str::upper(Str::substr($category->name, 0, 2));
            @endphp
            <li class="category-tile" data-category-name="{{ Str::lower($category->name) }}">
                <a href="{{ route('categories.show', $category) }}" class="category-tile__link">
                    <span class="category-tile__thumb" style="--accent: {{ $accent }}">
                        @if ($thumb)
                            <img src="{{ $thumb }}" alt="" class="category-tile__img" loading="lazy" decoding="async">
                        @else
                            <span class="category-tile__initial">{{ $initials }}</span>
                        @endif
                    </span>
                    <span class="category-tile__body">
                        <strong class="category-tile__name">{{ $category->name }}</strong>
                        <span class="category-tile__arrow" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="14" height="14">
                                <path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </span>
                    <span class="category-tile__accent-bar" aria-hidden="true"></span>
                </a>
            </li>
        @empty
            <li class="categories-empty">
                <strong>Каталог пока пуст</strong>
                <p>Категории появятся после наполнения каталога.</p>
            </li>
        @endforelse

        <li class="categories-empty" data-categories-empty hidden>
            <strong>Ничего не найдено</strong>
            <p>Попробуйте другой запрос.</p>
        </li>
    </ul>
</div>

<script>
    (function () {
        var input = document.querySelector('[data-categories-search]');
        var rows = document.querySelectorAll('.category-tile[data-category-name]');
        var emptyHint = document.querySelector('[data-categories-empty]');
        if (! input || ! rows.length) return;

        var apply = function () {
            var q = input.value.trim().toLowerCase();
            var visible = 0;
            rows.forEach(function (row) {
                var match = ! q || row.getAttribute('data-category-name').indexOf(q) !== -1;
                row.hidden = ! match;
                if (match) visible++;
            });
            if (emptyHint) emptyHint.hidden = visible !== 0;
        };

        input.addEventListener('input', apply, { passive: true });
    })();
</script>
@endsection
