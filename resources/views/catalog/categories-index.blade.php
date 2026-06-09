@extends('layouts.app')

@section('title', 'Категории · МАЖОР')
@section('meta_description', 'Каталог категорий МАЖОР — оптовые поставки строительных и отделочных материалов с актуальными ценами для бизнес-клиентов.')

@php
    use Illuminate\Support\Str;

    $accentPalette = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#14b8a6', '#0ea5e9', '#6366f1', '#a855f7', '#ec4899'];
@endphp

@section('content')
<style>
    /* Полностью изолированные стили для страницы категорий v3 — никаких legacy зависимостей. */
    .mj-cats {
        display: block;
        margin: 0;
        padding: 1rem 1rem 2rem;
        background: #ffffff;
        min-height: 0;
    }
    .mj-cats__head {
        display: block;
        margin: 0 0 12px;
    }
    .mj-cats__title {
        margin: 0 0 12px;
        font-family: 'IBM Plex Sans', system-ui, sans-serif;
        font-size: 22px;
        font-weight: 800;
        color: #0b1220;
        letter-spacing: -0.01em;
    }
    .mj-cats__search {
        position: relative;
        display: block;
        margin: 0 0 14px;
    }
    .mj-cats__search-input {
        display: block;
        width: 100%;
        height: 44px;
        padding: 0 14px 0 40px;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        background: #f6f7fa;
        font: 500 15px/1.2 system-ui, sans-serif;
        color: #0b1220;
        outline: none;
        box-sizing: border-box;
        -webkit-appearance: none;
    }
    .mj-cats__search-input:focus {
        background: #ffffff;
        border-color: #d60000;
    }
    .mj-cats__search-input::placeholder { color: #94a3b8; }
    .mj-cats__search-icon {
        position: absolute;
        top: 50%;
        left: 14px;
        transform: translateY(-50%);
        width: 18px;
        height: 18px;
        color: #64748b;
        pointer-events: none;
    }

    .mj-cats__featured {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 0 0 14px;
        padding: 12px 14px;
        background: linear-gradient(135deg, #ffffff 0%, #ffe5e5 100%);
        border: 1px solid rgba(214, 0, 0, 0.18);
        border-radius: 14px;
        text-decoration: none;
        color: #0b1220;
    }
    .mj-cats__featured-icon {
        flex: 0 0 auto;
        width: 36px; height: 36px;
        border-radius: 10px;
        background: #d60000;
        color: #fff;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .mj-cats__featured-icon svg { width: 18px; height: 18px; }
    .mj-cats__featured-body { flex: 1; min-width: 0; }
    .mj-cats__featured-body strong { display: block; font-size: 14px; font-weight: 700; line-height: 1.2; }
    .mj-cats__featured-body span { display: block; font-size: 12px; line-height: 1.35; color: #64748b; margin-top: 2px; }
    .mj-cats__featured-arrow { color: #d60000; font-size: 22px; line-height: 1; }

    .mj-cats__grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        grid-auto-rows: auto;
        align-content: start;
        align-items: start;
        gap: 12px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    @media (max-width: 360px) {
        .mj-cats__grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    }
    @media (min-width: 540px) and (max-width: 1023px) {
        .mj-cats__grid { grid-template-columns: repeat(4, 1fr); gap: 14px; }
    }
    @media (min-width: 1024px) {
        .mj-cats__grid { grid-template-columns: repeat(5, 1fr); gap: 18px; max-width: 1280px; margin-left: auto; margin-right: auto; }
    }
    @media (min-width: 1440px) {
        .mj-cats__grid { grid-template-columns: repeat(6, 1fr); }
    }

    .mj-cats__tile {
        margin: 0; padding: 0;
        list-style: none;
        height: auto;
        min-height: 0;
        align-self: start;
    }
    .mj-cats__tile-link {
        position: relative;
        display: block;
        background: #ffffff;
        border: 1px solid #eef0f3;
        border-radius: 14px;
        overflow: hidden;
        text-decoration: none;
        color: inherit;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05), 0 6px 14px -10px rgba(15, 23, 42, 0.12);
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .mj-cats__tile-link:hover { transform: translateY(-2px); }

    .mj-cats__tile-thumb {
        display: block;
        width: 100%;
        aspect-ratio: 1 / 1;
        background: #f6f7fa;
        position: relative;
        overflow: hidden;
    }
    .mj-cats__tile-img {
        position: absolute;
        inset: 11%;
        width: 78%; height: 78%;
        object-fit: contain;
    }
    .mj-cats__tile-initial {
        position: absolute;
        inset: 0;
        display: flex; align-items: center; justify-content: center;
        font-family: 'IBM Plex Sans', system-ui, sans-serif;
        font-weight: 800;
        font-size: clamp(1.4rem, 6vw, 2rem);
        color: var(--accent, #cbd5e1);
        letter-spacing: 0.02em;
    }
    .mj-cats__tile-body {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
        padding: 8px 10px 10px;
    }
    .mj-cats__tile-name {
        flex: 1;
        font: 700 13px/1.25 system-ui, sans-serif;
        color: #0b1220;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .mj-cats__tile-arrow {
        flex: 0 0 auto;
        width: 22px; height: 22px;
        border-radius: 999px;
        background: rgba(214, 0, 0, 0.08);
        color: #d60000;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .mj-cats__tile-accent {
        display: block;
        height: 3px;
        background: #d60000;
        opacity: 0.85;
    }

    .mj-cats__empty {
        grid-column: 1 / -1;
        padding: 24px;
        text-align: center;
        color: #64748b;
    }
    .mj-cats__empty strong { display: block; color: #0b1220; font-size: 16px; margin-bottom: 4px; }
    .mj-cats__empty[hidden] { display: none; }

    .mj-cats__tile[hidden] { display: none; }
</style>

<section class="mj-cats">
    <div class="mj-cats__head">
        <h1 class="mj-cats__title">Категории</h1>
        <div class="mj-cats__search">
            <svg viewBox="0 0 24 24" class="mj-cats__search-icon" aria-hidden="true">
                <circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/>
                <path d="M16.5 16.5L21 21" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <input
                type="search"
                placeholder="Поиск по категориям"
                class="mj-cats__search-input"
                autocomplete="off"
                data-mj-cats-search
                aria-label="Поиск по категориям"
            >
        </div>
    </div>

    @auth
        <a href="{{ route('orders.index') }}" class="mj-cats__featured">
            <span class="mj-cats__featured-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M3 12a9 9 0 1 0 3-6.7M3 4v5h5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <span class="mj-cats__featured-body">
                <strong>Уже заказывали</strong>
                <span>Быстро повторить покупки из прошлых заказов</span>
            </span>
            <span class="mj-cats__featured-arrow" aria-hidden="true">›</span>
        </a>
    @endauth

    <ul class="mj-cats__grid" data-mj-cats-list>
        @forelse ($categories as $index => $category)
            @php
                $accent = $category->accent_color && preg_match('/^#[0-9a-fA-F]{3,8}$/', $category->accent_color)
                    ? $category->accent_color
                    : $accentPalette[$index % count($accentPalette)];
                $thumb = $thumbnails[$category->id] ?? null;
                $initials = Str::upper(Str::substr($category->name, 0, 2));
            @endphp
            <li class="mj-cats__tile" data-mj-cats-name="{{ Str::lower($category->name) }}">
                <a href="{{ route('categories.show', $category) }}" class="mj-cats__tile-link" style="--accent: {{ $accent }}">
                    <span class="mj-cats__tile-thumb">
                        @if ($thumb)
                            <img src="{{ $thumb }}" alt="" class="mj-cats__tile-img" loading="lazy" decoding="async">
                        @else
                            <span class="mj-cats__tile-initial">{{ $initials }}</span>
                        @endif
                    </span>
                    <span class="mj-cats__tile-body">
                        <strong class="mj-cats__tile-name">{{ $category->name }}</strong>
                        <span class="mj-cats__tile-arrow" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="14" height="14">
                                <path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </span>
                    <span class="mj-cats__tile-accent" aria-hidden="true"></span>
                </a>
            </li>
        @empty
            <li class="mj-cats__empty">
                <strong>Каталог пока пуст</strong>
                <p>Категории появятся после наполнения каталога.</p>
            </li>
        @endforelse

        <li class="mj-cats__empty" data-mj-cats-empty hidden>
            <strong>Ничего не найдено</strong>
            <p>Попробуйте другой запрос.</p>
        </li>
    </ul>
</section>

<script>
    (function () {
        var input = document.querySelector('[data-mj-cats-search]');
        var tiles = document.querySelectorAll('.mj-cats__tile[data-mj-cats-name]');
        var emptyHint = document.querySelector('[data-mj-cats-empty]');
        if (!input || !tiles.length) return;

        function apply() {
            var q = input.value.trim().toLowerCase();
            var visible = 0;
            tiles.forEach(function (tile) {
                var match = !q || tile.getAttribute('data-mj-cats-name').indexOf(q) !== -1;
                tile.hidden = !match;
                if (match) visible++;
            });
            if (emptyHint) emptyHint.hidden = visible !== 0;
        }

        input.addEventListener('input', apply, { passive: true });
    })();
</script>
@endsection
