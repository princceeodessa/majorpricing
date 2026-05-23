@php
    /**
     * Пример использования:
     * @include('partials.breadcrumbs', ['items' => [
     *     ['label' => 'Каталог', 'url' => route('catalog.index')],
     *     ['label' => $category->name, 'url' => route('categories.show', $category)],
     *     ['label' => $product->name],   // последний — без url
     * ]])
     */
    $items = $items ?? [];
@endphp

@if (!empty($items))
    <nav class="catalog-breadcrumbs" aria-label="Хлебные крошки">
        <ol class="catalog-breadcrumbs__list" itemscope itemtype="https://schema.org/BreadcrumbList">
            <li class="catalog-breadcrumbs__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <a href="{{ route('catalog.index') }}" itemprop="item" class="catalog-breadcrumbs__link">
                    <svg viewBox="0 0 24 24" aria-hidden="true" class="catalog-breadcrumbs__home">
                        <path d="M4 10.5 12 4l8 6.5v8.2a1.3 1.3 0 0 1-1.3 1.3h-3.9v-6.1H9.2V20H5.3A1.3 1.3 0 0 1 4 18.7Z"/>
                    </svg>
                    <span itemprop="name">Главная</span>
                </a>
                <meta itemprop="position" content="1" />
            </li>

            @foreach ($items as $i => $crumb)
                @php
                    $isLast = $loop->last;
                    $position = $i + 2;
                @endphp
                <li class="catalog-breadcrumbs__item {{ $isLast ? 'is-current' : '' }}"
                    itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"
                    @if ($isLast) aria-current="page" @endif>
                    <span class="catalog-breadcrumbs__sep" aria-hidden="true">/</span>
                    @if (!$isLast && filled($crumb['url'] ?? null))
                        <a href="{{ $crumb['url'] }}" itemprop="item" class="catalog-breadcrumbs__link">
                            <span itemprop="name">{{ $crumb['label'] }}</span>
                        </a>
                    @else
                        <span itemprop="name" class="catalog-breadcrumbs__current">{{ $crumb['label'] }}</span>
                    @endif
                    <meta itemprop="position" content="{{ $position }}" />
                </li>
            @endforeach
        </ol>
    </nav>
@endif
