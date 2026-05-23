@php
    $shouldShow = auth()->check()
        && ($headerCartCount ?? 0) > 0
        && ! request()->routeIs('cart.*', 'login*', 'register*');

    $pluralRu = function (int $n, array $forms): string {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) return $forms[2];
        if ($n1 > 1 && $n1 < 5) return $forms[1];
        if ($n1 === 1) return $forms[0];
        return $forms[2];
    };
@endphp

@if ($shouldShow)
    <a
        href="{{ route('cart.index') }}"
        class="catalog-sticky-cart"
        role="button"
        aria-label="Перейти к оформлению заказа"
    >
        <span class="catalog-sticky-cart__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
                <path d="M4 7h15l-1.3 8.2a2 2 0 0 1-2 1.7H8.4a2 2 0 0 1-2-1.6L4.8 5.5H2.5" />
                <circle cx="9.2" cy="20" r="1.3" />
                <circle cx="16.4" cy="20" r="1.3" />
            </svg>
            <strong class="catalog-sticky-cart__badge" data-cart-count>{{ $headerCartCount }}</strong>
        </span>
        <span class="catalog-sticky-cart__content">
            <span class="catalog-sticky-cart__title">В корзине</span>
            <span class="catalog-sticky-cart__meta" data-cart-meta>
                {{ $headerCartCount }} {{ $pluralRu($headerCartCount, ['товар', 'товара', 'товаров']) }}
            </span>
        </span>
        <span class="catalog-sticky-cart__cta">
            К оформлению
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M9 6l6 6-6 6" />
            </svg>
        </span>
    </a>
@endif
