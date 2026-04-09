@extends('layouts.app')

@section('title', 'Диагностика 1С ПОТОЛКОВЫЧ')

@section('content')
    @if ($lastImportReport)
        <section class="surface-card mb-8 border border-rose-200/80 bg-rose-50/80 p-6">
            <span class="soft-badge">Последний импорт</span>
            <h2 class="mt-4 font-['IBM_Plex_Sans'] text-2xl font-semibold text-slate-950">Почему пакет не импортировался полностью</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-4">
                <div class="stat-card">
                    <span>Категорий</span>
                    <strong>{{ $lastImportReport['categories'] }}</strong>
                </div>
                <div class="stat-card">
                    <span>Товаров</span>
                    <strong>{{ $lastImportReport['products'] }}</strong>
                </div>
                <div class="stat-card">
                    <span>Цен</span>
                    <strong>{{ $lastImportReport['prices'] }}</strong>
                </div>
                <div class="stat-card">
                    <span>Без привязки</span>
                    <strong>{{ $lastImportReport['offers_without_products'] }}</strong>
                </div>
            </div>

            @if ($lastImportReport['warnings'] !== [])
                <div class="mt-5 space-y-2 text-sm leading-6 text-slate-700">
                    @foreach ($lastImportReport['warnings'] as $warning)
                        <p class="rounded-2xl border border-rose-200/80 bg-white px-4 py-3">{{ $warning }}</p>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    <section class="surface-card p-8 sm:p-10">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl">
                <span class="soft-badge">Диагностика 1С</span>
                <h1 class="mt-4 font-['IBM_Plex_Sans'] text-4xl font-semibold text-slate-950">Проверка штатного обмена с 1С</h1>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-600">
                    Здесь менеджер видит, настроен ли endpoint обмена, приходят ли файлы CommerceML, появились ли товары и цены из 1С,
                    а также ушли ли заказы обратно в 1С. Для разбора можно открыть XML прямо в браузере или скачать весь пакет одним архивом.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('account.show') }}" class="ghost-button">Менеджерский кабинет</a>
                <a href="{{ route('orders.index') }}" class="action-button">Открыть заказы</a>
            </div>
        </div>
    </section>

    <section class="mt-8 grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <div class="surface-card p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <span class="soft-badge">Подключение</span>
                    <h2 class="mt-4 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Параметры обмена</h2>
                </div>
                @if ($catalogPackages->isNotEmpty())
                    <form action="{{ route('manager.onec.catalog.import') }}" method="POST">
                        @csrf
                        <input type="hidden" name="session_key" value="{{ $catalogPackages->first()['session_key'] }}">
                        <button type="submit" class="action-button">Импортировать последний каталог</button>
                    </form>
                @endif
            </div>

            <div class="mt-6 grid gap-4">
                <div class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-5">
                    <span class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-400">Основной URL</span>
                    <p class="mt-2 break-all font-mono text-sm text-slate-900">{{ $exchangeUrl }}</p>
                </div>

                <div class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/80 p-5">
                    <span class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-400">Запасной URL</span>
                    <p class="mt-2 break-all font-mono text-sm text-slate-900">{{ $fallbackExchangeUrl }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-[1.5rem] border border-slate-200/80 bg-white p-5">
                        <span class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-400">Логин</span>
                        <p class="mt-3 text-lg font-semibold text-slate-950">{{ $oneCSettings['username'] }}</p>
                    </div>
                    <div class="rounded-[1.5rem] border border-slate-200/80 bg-white p-5">
                        <span class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-400">Пароль</span>
                        <p class="mt-3 text-lg font-semibold {{ $oneCSettings['password_configured'] ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ $oneCSettings['password_configured'] ? 'Задан' : 'Не задан' }}
                        </p>
                    </div>
                    <div class="rounded-[1.5rem] border border-slate-200/80 bg-white p-5">
                        <span class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-400">Лимит файла</span>
                        <p class="mt-3 text-lg font-semibold text-slate-950">{{ $oneCSettings['file_limit'] }}</p>
                    </div>
                </div>

                <div class="rounded-[1.5rem] border border-slate-200/80 bg-white p-5">
                    <span class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-400">Каталог временных файлов</span>
                    <p class="mt-2 break-all font-mono text-sm text-slate-900">storage/app/{{ $oneCSettings['upload_dir'] }}</p>
                </div>
            </div>
        </div>

        <div class="surface-card p-8">
            <span class="soft-badge">Статус</span>
            <h2 class="mt-4 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Что уже приехало из 1С</h2>

            <div class="mt-6 grid gap-4">
                <div class="stat-card">
                    <span>Категорий с 1С ID</span>
                    <strong>{{ $stats['categories'] }}</strong>
                </div>
                <div class="stat-card">
                    <span>Товаров с 1С ID</span>
                    <strong>{{ $stats['products'] }}</strong>
                </div>
                <div class="stat-card">
                    <span>Типов цен</span>
                    <strong>{{ $stats['price_types'] }}</strong>
                </div>
                <div class="stat-card">
                    <span>Заказов ждут выгрузки</span>
                    <strong>{{ $stats['orders_pending_export'] }}</strong>
                </div>
                <div class="stat-card">
                    <span>Заказов выгружено</span>
                    <strong>{{ $stats['orders_exported'] }}</strong>
                </div>
                <div class="stat-card">
                    <span>Заказов с номером 1С</span>
                    <strong>{{ $stats['orders_with_document'] }}</strong>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-8 grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <div class="surface-card p-8">
            <span class="soft-badge">Каталог 1С</span>
            <h2 class="mt-4 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Пакеты для импорта товаров</h2>

            @if ($catalogPackages->isEmpty())
                <div class="mt-6 rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50/70 p-6 text-sm text-slate-500">
                    Каталожных пакетов от 1С пока нет. После выгрузки товаров здесь появится последний session key, и можно будет открыть или скачать файлы пакета.
                </div>
            @else
                <div class="mt-6 space-y-3">
                    @foreach ($catalogPackages as $package)
                        <article class="rounded-[1.5rem] border border-slate-200/80 bg-white p-5">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <h3 class="text-base font-semibold text-slate-950">Пакет {{ $package['session_key'] }}</h3>
                                    <p class="mt-2 text-sm text-slate-600">Обновлён: {{ $package['modified_at'] ?: '—' }}</p>

                                    <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-500">
                                        @foreach ($package['files'] as $filename)
                                            <a href="{{ route('manager.onec.catalog.file', ['session_key' => $package['session_key'], 'filename' => $filename]) }}" class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 transition hover:border-slate-300 hover:bg-white hover:text-slate-900">{{ $filename }}</a>
                                        @endforeach
                                    </div>

                                    @if ($package['notes'] !== [])
                                        <div class="mt-4 space-y-2 text-sm text-amber-700">
                                            @foreach ($package['notes'] as $note)
                                                <p class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">{{ $note }}</p>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="flex flex-wrap gap-3">
                                    @if (in_array('import.xml', $package['files'], true))
                                        <a href="{{ route('manager.onec.catalog.file', ['session_key' => $package['session_key'], 'filename' => 'import.xml']) }}" class="soft-badge {{ $package['has_import'] ? '' : 'opacity-60' }}">import.xml</a>
                                    @else
                                        <span class="soft-badge opacity-60">import.xml</span>
                                    @endif

                                    @if (in_array('offers.xml', $package['files'], true))
                                        <a href="{{ route('manager.onec.catalog.file', ['session_key' => $package['session_key'], 'filename' => 'offers.xml']) }}" class="soft-badge {{ $package['has_offers'] ? '' : 'opacity-60' }}">offers.xml</a>
                                    @else
                                        <span class="soft-badge opacity-60">offers.xml</span>
                                    @endif

                                    <a href="{{ route('manager.onec.catalog.package.download', ['session_key' => $package['session_key']]) }}" class="soft-badge">Скачать пакет</a>

                                    <form action="{{ route('manager.onec.catalog.import') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="session_key" value="{{ $package['session_key'] }}">
                                        <button type="submit" class="ghost-button">Импортировать</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="surface-card p-8">
            <span class="soft-badge">Последние файлы</span>
            <h2 class="mt-4 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Пакеты обмена</h2>

            @if ($recentFiles->isEmpty())
                <div class="mt-6 rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50/70 p-6 text-sm text-slate-500">
                    Пока нет сохранённых файлов обмена. После первой выгрузки из 1С здесь появятся import.xml, offers.xml и файлы заказов.
                </div>
            @else
                <div class="mt-6 overflow-hidden rounded-[1.75rem] border border-slate-200/80">
                    <table class="w-full min-w-[720px] border-collapse text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-5 py-4 font-semibold">Файл</th>
                                <th class="px-5 py-4 font-semibold">Тип</th>
                                <th class="px-5 py-4 font-semibold">Пакет</th>
                                <th class="px-5 py-4 font-semibold">Размер</th>
                                <th class="px-5 py-4 font-semibold">Изменён</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentFiles as $file)
                                <tr class="border-t border-slate-200/80 bg-white">
                                    <td class="px-5 py-4 font-medium text-slate-900">{{ $file['filename'] }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $file['type'] ?: '—' }}</td>
                                    <td class="px-5 py-4 font-mono text-xs text-slate-500">{{ $file['session_key'] ?: '—' }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $file['size'] }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $file['modified_at'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="surface-card p-8 xl:col-span-2">
            <span class="soft-badge">Как тестировать</span>
            <h2 class="mt-4 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Быстрый сценарий</h2>
            <ol class="mt-6 space-y-4 text-sm leading-7 text-slate-700">
                <li>1. В менеджерском кабинете создайте обычного тестового клиента.</li>
                <li>2. В 1С запустите полную выгрузку товаров и цен на сайт.</li>
                <li>3. Если 1С прислала файлы, но товары на сайт не появились, нажмите `Импортировать последний каталог` или `Импортировать` напротив нужного пакета.</li>
                <li>4. Если нужно проверить содержимое обмена, откройте `import.xml` и `offers.xml` либо скачайте пакет целиком.</li>
                <li>5. Войдите под тестовым клиентом, добавьте товар в корзину и оформите тестовый заказ.</li>
                <li>6. Запустите обмен заказами в 1С и проверьте, что число `Заказов выгружено` увеличилось.</li>
            </ol>
        </div>
    </section>

    <section class="mt-8 grid gap-6 xl:grid-cols-2">
        <div class="surface-card p-8">
            <span class="soft-badge">Последние товары</span>
            <h2 class="mt-4 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Импорт каталога</h2>

            <div class="mt-6 space-y-3">
                @forelse ($recentProducts as $product)
                    <article class="rounded-[1.5rem] border border-slate-200/80 bg-white p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-base font-semibold text-slate-950">{{ $product->title }}</h3>
                                <p class="mt-2 font-mono text-xs text-slate-500">{{ $product->one_c_id }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs uppercase tracking-[0.22em] text-slate-400">Цена от</p>
                                <strong class="mt-2 block text-lg text-slate-950">{{ $product->price_from ? number_format((float) $product->price_from, 2, ',', ' ') . ' ₽' : '—' }}</strong>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-4 text-sm text-slate-600">
                            <span>Артикул: {{ $product->vendor_code ?: '—' }}</span>
                            <span>Обновлён: {{ optional($product->updated_at)->format('d.m.Y H:i') }}</span>
                        </div>
                    </article>
                @empty
                    <div class="rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50/70 p-6 text-sm text-slate-500">
                        Товары из 1С пока не импортировались.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="surface-card p-8">
            <span class="soft-badge">Последние заказы</span>
            <h2 class="mt-4 font-['IBM_Plex_Sans'] text-3xl font-semibold text-slate-950">Обмен заказами</h2>

            <div class="mt-6 space-y-3">
                @forelse ($recentOrders as $order)
                    <article class="rounded-[1.5rem] border border-slate-200/80 bg-white p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-base font-semibold text-slate-950">{{ $order->number }}</h3>
                                <p class="mt-2 text-sm text-slate-600">Статус: {{ \App\Support\OrderStatuses::label($order->status) }} · Оплата: {{ $order->payment_status }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs uppercase tracking-[0.22em] text-slate-400">1С документ</p>
                                <strong class="mt-2 block text-sm text-slate-950">{{ $order->one_c_document_id ?: '—' }}</strong>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-4 text-sm text-slate-600">
                            <span>Выгружен: {{ $order->one_c_exported_at ? $order->one_c_exported_at->format('d.m.Y H:i') : 'нет' }}</span>
                            <span>Обновлён: {{ optional($order->updated_at)->format('d.m.Y H:i') }}</span>
                        </div>
                    </article>
                @empty
                    <div class="rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50/70 p-6 text-sm text-slate-500">
                        Заказов для проверки обмена пока нет.
                    </div>
                @endforelse
            </div>
        </div>
    </section>
@endsection
