@php
    $isFavorite = in_array($product->id, $favoriteProductIds ?? [], true);
    $sizeClass = $sizeClass ?? '';
    $showLabel = $showLabel ?? false;
    $favoritePayload = [
        'productId' => $product->id,
        'favorited' => $isFavorite,
        'storeUrl' => route('favorites.store', $product),
        'destroyUrl' => route('favorites.destroy', $product),
        'csrfToken' => csrf_token(),
        'showLabel' => (bool) $showLabel,
    ];
@endphp

<form
    action="{{ $isFavorite ? route('favorites.destroy', $product) : route('favorites.store', $product) }}"
    method="POST"
    class="catalog-favorite-form {{ $sizeClass }}"
    data-favorite-form
    data-vue-favorite
    data-vue-favorite-props='@json($favoritePayload, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)'
    data-product-id="{{ $product->id }}"
    data-store-url="{{ route('favorites.store', $product) }}"
    data-destroy-url="{{ route('favorites.destroy', $product) }}"
    data-favorited="{{ $isFavorite ? '1' : '0' }}"
>
    @csrf
    @if ($isFavorite)
        <input type="hidden" name="_method" value="DELETE" data-favorite-method>
    @endif

    <button
        type="submit"
        class="catalog-favorite-button {{ $isFavorite ? 'is-active' : '' }}"
        data-favorite-button
        aria-label="{{ $isFavorite ? 'Убрать из избранного' : 'Добавить в избранное' }}"
        title="{{ $isFavorite ? 'Убрать из избранного' : 'Добавить в избранное' }}"
    >
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 21.35 10.55 20C5.4 15.24 2 12.09 2 8.25 2 5.1 4.42 2.75 7.5 2.75c1.74 0 3.41.81 4.5 2.09 1.09-1.28 2.76-2.09 4.5-2.09 3.08 0 5.5 2.35 5.5 5.5 0 3.84-3.4 6.99-8.55 11.76L12 21.35Z"/>
        </svg>
        @if ($showLabel)
            <span data-favorite-label>{{ $isFavorite ? 'В избранном' : 'В избранное' }}</span>
        @endif
    </button>
</form>
