@if ($nextPageUrl)
    <div class="catalog-infinite-trigger" data-infinite-trigger>
        <div class="catalog-infinite-loader hidden" data-infinite-loader>
            <span class="catalog-infinite-loader__dot"></span>
            <span class="catalog-infinite-loader__dot"></span>
            <span class="catalog-infinite-loader__dot"></span>
            <span class="catalog-infinite-loader__label">{{ $loadingLabel ?? 'Подгружаем еще товары' }}</span>
        </div>

        <button type="button" class="catalog-infinite-button hidden" data-infinite-button>
            {{ $fallbackLabel ?? 'Показать еще' }}
        </button>
    </div>
@endif
