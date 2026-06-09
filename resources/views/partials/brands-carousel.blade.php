@php
    /** @var \Illuminate\Support\Collection $brands */
    $brands = $brands ?? collect();
    /** @var \Illuminate\Support\Collection $pendingBrands */
    $pendingBrands = $pendingBrands ?? collect();
    $selectedBrand = $selectedBrand ?? null;

    $resolveLogo = function (string $slug) {
        foreach (["brand/logos/{$slug}.svg", "brand/logos/{$slug}.png", "brand/logos/{$slug}.webp"] as $candidate) {
            if (file_exists(public_path($candidate))) {
                return $candidate;
            }
        }
        return null;
    };
@endphp

@if ($brands->isNotEmpty() || $pendingBrands->isNotEmpty())
    <section
        class="brands-carousel-section"
        aria-label="Бренды каталога"
    >
        <div class="brands-carousel-head">
            <h2 class="brands-carousel-title">Бренды</h2>
            @if ($selectedBrand)
                <a href="{{ route('catalog.index') }}" class="brands-carousel-reset">
                    Сбросить «{{ $selectedBrand }}»
                </a>
            @endif
        </div>

        <div class="brands-carousel-track" data-brands-carousel>
            @foreach ($brands as $brand)
                @php
                    $isActive = $selectedBrand === $brand['name'];
                    $brandSlug = \Illuminate\Support\Str::slug($brand['name']);
                    $logoPath = $resolveLogo($brandSlug) ?? 'brand/major-logo-wide.svg';
                @endphp
                <a
                    href="{{ $brand['url'] }}"
                    class="brand-card {{ $isActive ? 'is-active' : '' }}"
                    aria-label="{{ $brand['name'] }} — {{ $brand['count'] }} товаров"
                    data-brand="{{ $brand['name'] }}"
                >
                    <img
                        src="{{ asset($logoPath) }}"
                        alt="{{ $brand['name'] }}"
                        class="brand-card__logo"
                        loading="lazy"
                        decoding="async"
                    >
                </a>
            @endforeach

            {{-- Pending-бренды: лого есть, в БД ещё нет. Некликабельные заглушки. --}}
            @foreach ($pendingBrands as $brand)
                @php
                    $logoPath = $resolveLogo($brand['slug']) ?? 'brand/major-logo-wide.svg';
                @endphp
                <div
                    class="brand-card brand-card--pending"
                    aria-label="{{ $brand['name'] }} — скоро в каталоге"
                    role="img"
                    data-brand="{{ $brand['name'] }}"
                    title="{{ $brand['name'] }} — скоро появится"
                >
                    <img
                        src="{{ asset($logoPath) }}"
                        alt="{{ $brand['name'] }}"
                        class="brand-card__logo"
                        loading="lazy"
                        decoding="async"
                    >
                </div>
            @endforeach
        </div>
    </section>
@endif
