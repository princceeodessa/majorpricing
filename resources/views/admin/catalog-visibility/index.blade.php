@extends('layouts.app')

@section('title', 'Видимость каталога - ПОТОЛКОВЫЧ')

@section('content')
    <section class="surface-card reveal-card p-5 sm:p-8">
        <div class="catalog-page-head">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Администрирование</p>
                <h1 class="mt-2 font-['IBM_Plex_Sans'] text-3xl font-semibold tracking-tight text-slate-950">Видимость каталога</h1>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">
                    Отметьте категории, которые нужно скрыть от клиентов. Если скрыть родительскую категорию, все дочерние категории и товары внутри них также исчезнут из каталога, поиска, карточек и корзины.
                </p>
            </div>
            <a href="{{ route('account.show') }}" class="ghost-button">Назад в админку</a>
        </div>

        @if (session('status'))
            <div class="mt-6 rounded-[22px] border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="mt-6 grid gap-3 sm:grid-cols-4">
            <div class="rounded-[22px] border border-slate-200 bg-white/90 p-4">
                <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Категорий</span>
                <strong class="mt-2 block text-2xl font-semibold text-slate-950">{{ $stats['categories'] }}</strong>
            </div>
            <div class="rounded-[22px] border border-slate-200 bg-white/90 p-4">
                <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Скрыто вручную</span>
                <strong class="mt-2 block text-2xl font-semibold text-slate-950">{{ $stats['directlyHidden'] }}</strong>
            </div>
            <div class="rounded-[22px] border border-slate-200 bg-white/90 p-4">
                <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Скрыто всего</span>
                <strong class="mt-2 block text-2xl font-semibold text-slate-950">{{ $stats['effectivelyHidden'] }}</strong>
            </div>
            <div class="rounded-[22px] border border-slate-200 bg-white/90 p-4">
                <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Товаров скрыто</span>
                <strong class="mt-2 block text-2xl font-semibold text-slate-950">{{ $stats['hiddenProducts'] }}</strong>
            </div>
        </div>

        <form action="{{ route('admin.catalog.visibility.update') }}" method="POST" class="mt-6">
            @csrf
            @method('PATCH')

            <div class="overflow-x-auto rounded-[28px] border border-slate-200 bg-white/95">
                <div class="min-w-[780px]">
                    <div class="grid grid-cols-[minmax(0,1fr)_140px_150px_170px] gap-3 border-b border-slate-200 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-[0.16em] text-slate-500">
                        <span>Категория</span>
                        <span>В ветке</span>
                        <span>Статус</span>
                        <span class="text-right">Скрыть</span>
                    </div>

                    @forelse ($categoryRows as $category)
                        <label class="grid cursor-pointer grid-cols-[minmax(0,1fr)_140px_150px_170px] items-center gap-3 border-b border-slate-100 px-5 py-4 transition hover:bg-slate-50/80">
                            <span class="min-w-0" style="padding-left: {{ $category->visibility_depth * 1.5 }}rem">
                                <span class="block truncate text-sm font-semibold text-slate-950">{{ $category->name }}</span>
                                <span class="mt-1 block text-xs text-slate-500">
                                    Товаров напрямую: {{ $category->products_count }}
                                </span>
                            </span>

                            <span class="text-sm font-semibold text-slate-700">{{ $category->branch_products_count }}</span>

                            <span>
                                @if ($category->is_hidden_from_clients)
                                    <span class="inline-flex min-h-8 items-center rounded-full bg-rose-50 px-3 text-xs font-bold text-rose-700">Скрыто вручную</span>
                                @elseif ($category->is_effectively_hidden_from_clients)
                                    <span class="inline-flex min-h-8 items-center rounded-full bg-amber-50 px-3 text-xs font-bold text-amber-700">Скрыто правилом</span>
                                @else
                                    <span class="inline-flex min-h-8 items-center rounded-full bg-emerald-50 px-3 text-xs font-bold text-emerald-700">Видно</span>
                                @endif
                            </span>

                            <span class="flex justify-end">
                                <input
                                    type="checkbox"
                                    name="hidden_categories[]"
                                    value="{{ $category->id }}"
                                    class="h-5 w-5 rounded border-slate-300 text-slate-950"
                                    @checked($category->is_hidden_from_clients)
                                >
                            </span>
                        </label>
                    @empty
                        <div class="p-8 text-center text-sm text-slate-600">
                            Категории пока не загружены.
                        </div>
                    @endforelse
                </div>
            </div>

            @error('hidden_categories')
                <p class="mt-3 text-sm font-semibold text-rose-600">{{ $message }}</p>
            @enderror

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm leading-6 text-slate-600">
                    Изменения применяются сразу после сохранения. Повторный импорт 1С не сбрасывает ручную видимость.
                </p>
                <button type="submit" class="action-button px-7">Сохранить видимость</button>
            </div>
        </form>
    </section>
@endsection
