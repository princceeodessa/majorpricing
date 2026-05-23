@php
    $authedFooter = auth()->check();
@endphp

<footer class="catalog-footer" role="contentinfo">
    <div class="catalog-footer__shell">
        <div class="catalog-footer__brand">
            <img src="{{ asset('brand/major-logo-wide.svg') }}" alt="МАЖОР" class="catalog-footer__logo">
            <p class="catalog-footer__tagline">
                B2B-каталог МАЖОР — оптовые поставки строительных и отделочных материалов с прямой ценой и быстрым сопровождением менеджера.
            </p>
        </div>

        <div class="catalog-footer__col">
            <h3 class="catalog-footer__heading">Каталог</h3>
            <ul class="catalog-footer__links">
                <li><a href="{{ route('catalog.index') }}">Все категории</a></li>
                @if (($navCategories ?? collect())->isNotEmpty())
                    @foreach ($navCategories->take(5) as $navCategory)
                        <li><a href="{{ route('categories.show', $navCategory) }}">{{ $navCategory->name }}</a></li>
                    @endforeach
                @endif
            </ul>
        </div>

        <div class="catalog-footer__col">
            <h3 class="catalog-footer__heading">Клиентам</h3>
            <ul class="catalog-footer__links">
                @if ($authedFooter)
                    <li><a href="{{ route('account.show') }}">Личный кабинет</a></li>
                    <li><a href="{{ route('favorites.index') }}">Избранное</a></li>
                    <li><a href="{{ route('orders.index') }}">Мои заказы</a></li>
                    <li><a href="{{ route('cart.index') }}">Корзина</a></li>
                @else
                    <li><a href="{{ route('login') }}">Войти</a></li>
                    <li><a href="{{ route('registration-requests.create') }}">Стать партнёром</a></li>
                @endif
            </ul>
        </div>

        <div class="catalog-footer__col">
            <h3 class="catalog-footer__heading">Связь</h3>
            <ul class="catalog-footer__links catalog-footer__links--contact">
                <li>
                    <a href="tel:+78001003035" class="catalog-footer__contact">
                        <span class="catalog-footer__contact-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M5 4.5a1.5 1.5 0 0 1 1.5-1.5h2a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-.7c.7 3.5 3.4 6.2 6.9 6.9v-.7a1.5 1.5 0 0 1 1.5-1.5h3a1.5 1.5 0 0 1 1.5 1.5v2a1.5 1.5 0 0 1-1.5 1.5C10.3 19.7 4.3 13.7 5 4.5Z" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <span>
                            <strong>8 800 100-30-35</strong>
                            <small>пн–пт, 9:00–18:00</small>
                        </span>
                    </a>
                </li>
                <li>
                    <a href="mailto:hello@major.ru" class="catalog-footer__contact">
                        <span class="catalog-footer__contact-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><rect x="3" y="5.5" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="m3.5 7 8.5 6 8.5-6" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linecap="round"/></svg>
                        </span>
                        <span>
                            <strong>hello@major.ru</strong>
                            <small>ответим за час</small>
                        </span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="catalog-footer__bottom">
        <p class="catalog-footer__copy">© {{ date('Y') }} МАЖОР · Каталог для бизнес-клиентов</p>
        <p class="catalog-footer__legal">
            Цены в каталоге носят информационный характер. Финальное предложение готовит менеджер по запросу.
        </p>
    </div>
</footer>
