@extends('layouts.app')

@section('title', 'Фото товара - ПОТОЛКОВЫЧ')

@section('content')
    @php
        $fallbackImageUrl = asset('brand/product-placeholder.png');
        $coverPath = $product->coverImagePath();
        $coverUrl = $coverPath ? asset($coverPath) : $fallbackImageUrl;
    @endphp

    <section class="surface-card reveal-card p-5 sm:p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('admin.products.images.index') }}" class="inline-flex items-center text-sm font-semibold text-slate-600 hover:text-slate-900">
                    ← К списку товаров
                </a>
                <p class="mt-4 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Фото товара</p>
                <h1 class="mt-2 font-['IBM_Plex_Sans'] text-3xl font-semibold tracking-tight text-slate-950">{{ $product->publicTitle() }}</h1>
                <p class="mt-2 text-sm text-slate-600">
                    Категория: {{ $product->category?->name ?? 'Без категории' }}
                    @if (filled($product->vendor_code))
                        · Артикул: {{ $product->vendor_code }}
                    @endif
                </p>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            <div class="rounded-[24px] border border-slate-200 bg-white/96 p-4">
                <div class="aspect-[4/3] overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
                    <img
                        src="{{ $coverUrl }}"
                        alt="{{ $product->publicTitle() }}"
                        class="h-full w-full object-contain p-3"
                    >
                </div>
                <p class="mt-3 text-sm font-semibold text-slate-900">Текущая обложка</p>
            </div>

            <div class="rounded-[24px] border border-slate-200 bg-white/96 p-4">
                <h2 class="text-lg font-semibold text-slate-900">Добавить фотографии</h2>
                <p class="mt-2 text-sm text-slate-600">Можно загрузить до 20 файлов за раз (jpg, png, webp, avif, svg).</p>

                <form action="{{ route('admin.products.images.store', $product) }}" method="POST" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    <label class="access-field m-0">
                        <span>Файлы</span>
                        <input type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp,.avif,.svg" multiple required>
                    </label>
                    <button type="submit" class="action-button w-full justify-center">Загрузить</button>
                </form>
            </div>
        </div>

        <div class="mt-8">
            <h2 class="text-xl font-semibold text-slate-950">Все фото ({{ $product->productImages->count() }})</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @forelse ($product->productImages as $image)
                    <article class="rounded-[24px] border border-slate-200 bg-white/95 p-3 shadow-[0_18px_35px_-30px_rgba(15,23,42,0.18)]">
                        <div class="aspect-square overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
                            <img
                                src="{{ asset($image->path) }}"
                                alt="{{ $product->publicTitle() }}"
                                class="h-full w-full object-contain p-2"
                                loading="lazy"
                            >
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @if ($image->is_cover)
                                <span class="inline-flex min-h-8 items-center rounded-full bg-emerald-50 px-3 text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-700">
                                    Обложка
                                </span>
                            @else
                                <form action="{{ route('admin.products.images.cover', [$product, $image]) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="ghost-button min-h-8 px-3 py-2 text-xs">Сделать обложкой</button>
                                </form>
                            @endif

                            <form action="{{ route('admin.products.images.destroy', [$product, $image]) }}" method="POST" onsubmit="return confirm('Удалить фото?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="catalog-inline-action">Удалить</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="sm:col-span-2 xl:col-span-4 rounded-[24px] border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-slate-600">
                        У товара пока нет фото.
                    </div>
                @endforelse
            </div>
        </div>
    </section>
@endsection
