@extends('layouts.app')

@section('title', 'Фото товаров - ПОТОЛКОВЫЧ')

@section('content')
    <section class="surface-card reveal-card p-5 sm:p-8">
        <div class="catalog-page-head">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Администрирование</p>
                <h1 class="mt-2 font-['IBM_Plex_Sans'] text-3xl font-semibold tracking-tight text-slate-950">Фото товаров</h1>
                <p class="mt-3 text-sm leading-6 text-slate-600">
                    Выберите товар, добавьте фото и назначьте обложку.
                </p>
            </div>
        </div>

        <form action="{{ route('admin.products.images.index') }}" method="GET" class="mt-6 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
            <label class="access-field m-0">
                <span>Поиск товара</span>
                <input
                    type="text"
                    name="q"
                    value="{{ $query }}"
                    placeholder="Название, артикул, код 1С"
                >
            </label>
            <button type="submit" class="action-button min-h-[56px] px-7">Найти</button>
        </form>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($products as $product)
                @php
                    $coverPath = $product->coverImagePath();
                    $coverUrl = $coverPath ? asset($coverPath) : asset('brand/product-placeholder.png');
                @endphp

                <article class="rounded-[24px] border border-slate-200 bg-white/95 p-4 shadow-[0_18px_35px_-30px_rgba(15,23,42,0.2)]">
                    <div class="aspect-[16/10] overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
                        <img
                            src="{{ $coverUrl }}"
                            alt="{{ $product->publicTitle() }}"
                            class="h-full w-full object-contain p-2"
                            loading="lazy"
                        >
                    </div>

                    <p class="mt-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ $product->category?->name ?? 'Без категории' }}</p>
                    <h2 class="mt-2 line-clamp-2 text-lg font-semibold text-slate-900">{{ $product->publicTitle() }}</h2>

                    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-slate-600">
                        <span class="inline-flex min-h-8 items-center rounded-full border border-slate-200 bg-slate-50 px-3">
                            Фото: {{ $product->productImages->count() }}
                        </span>
                        @if (filled($product->vendor_code))
                            <span class="inline-flex min-h-8 items-center rounded-full border border-slate-200 bg-slate-50 px-3">
                                Арт: {{ $product->vendor_code }}
                            </span>
                        @endif
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('admin.products.images.edit', $product) }}" class="action-button w-full justify-center">
                            Управлять фото
                        </a>
                    </div>
                </article>
            @empty
                <div class="sm:col-span-2 xl:col-span-3 rounded-[24px] border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-slate-600">
                    Товары не найдены.
                </div>
            @endforelse
        </div>

        <div class="mt-6">
            {{ $products->links() }}
        </div>
    </section>
@endsection
