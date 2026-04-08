@extends('layouts.app')

@section('title', 'Просмотр пакета 1С')

@section('content')
    <section class="surface-card p-8 sm:p-10">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl">
                <span class="soft-badge">Файл 1С</span>
                <h1 class="mt-4 font-['IBM_Plex_Sans'] text-4xl font-semibold text-slate-950">{{ $filename }}</h1>
                <div class="mt-4 flex flex-wrap gap-3 text-sm text-slate-600">
                    <span>Пакет: <strong class="text-slate-950">{{ $sessionKey }}</strong></span>
                    <span>Тип: <strong class="text-slate-950">{{ $summary['detected_type'] === 'offers' ? 'Цены и предложения' : 'Каталог и товары' }}</strong></span>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ $exchangeUrl }}" class="ghost-button">Endpoint 1С</a>
                <a href="{{ $backUrl }}" class="action-button">Назад к диагностике</a>
            </div>
        </div>
    </section>

    <section class="mt-8 grid gap-6 xl:grid-cols-[0.85fr_1.15fr]">
        <div class="surface-card p-8">
            <span class="soft-badge">Разбор файла</span>
            <h2 class="mt-4 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Что пришло от 1С</h2>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="stat-card">
                    <span>Категорий</span>
                    <strong>{{ $summary['categories_count'] }}</strong>
                </div>
                <div class="stat-card">
                    <span>Товаров</span>
                    <strong>{{ $summary['products_count'] }}</strong>
                </div>
                <div class="stat-card">
                    <span>Предложений</span>
                    <strong>{{ $summary['offers_count'] }}</strong>
                </div>
                <div class="stat-card">
                    <span>Типов цен</span>
                    <strong>{{ $summary['price_types_count'] }}</strong>
                </div>
            </div>

            @if ($summary['warnings'] !== [])
                <div class="mt-6 space-y-2">
                    @foreach ($summary['warnings'] as $warning)
                        <p class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $warning }}</p>
                    @endforeach
                </div>
            @endif

            @if ($summary['category_names'] !== [])
                <div class="mt-6">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-400">Категории</h3>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($summary['category_names'] as $categoryName)
                            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-sm text-slate-700">{{ $categoryName }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($summary['product_rows'] !== [])
                <div class="mt-6">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-400">Первые товары</h3>
                    <div class="mt-3 space-y-3">
                        @foreach ($summary['product_rows'] as $row)
                            <article class="rounded-[1.25rem] border border-slate-200/80 bg-slate-50/70 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-950">{{ $row['name'] }}</p>
                                        <p class="mt-1 font-mono text-xs text-slate-500">{{ $row['id'] }}</p>
                                    </div>
                                    <span class="text-sm text-slate-600">Артикул: {{ $row['article'] }}</span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($summary['offer_rows'] !== [])
                <div class="mt-6">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-400">Первые предложения</h3>
                    <div class="mt-3 space-y-3">
                        @foreach ($summary['offer_rows'] as $row)
                            <article class="rounded-[1.25rem] border border-slate-200/80 bg-slate-50/70 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-950">{{ $row['id'] }}</p>
                                        <p class="mt-1 text-sm text-slate-600">Тип цены: {{ $row['price_type'] }}</p>
                                    </div>
                                    <span class="text-sm font-semibold text-slate-950">{{ $row['amount'] }}</span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="surface-card p-8">
            <span class="soft-badge">XML</span>
            <h2 class="mt-4 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Содержимое файла</h2>
            <div class="mt-6 overflow-x-auto rounded-[1.75rem] border border-slate-200/80 bg-slate-950 p-5">
                <pre class="whitespace-pre-wrap break-words font-mono text-xs leading-6 text-slate-100">{{ $content }}</pre>
            </div>
        </div>
    </section>
@endsection
