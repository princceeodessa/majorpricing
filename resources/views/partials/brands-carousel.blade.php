@php
    /** @var \Illuminate\Support\Collection $brands */
    $brands = $brands ?? collect();
    $selectedBrand = $selectedBrand ?? null;
@endphp

@if ($brands->isNotEmpty())
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
                    $logoCandidates = [
                        "brand/logos/{$brandSlug}.svg",
                        "brand/logos/{$brandSlug}.png",
                        "brand/logos/{$brandSlug}.webp",
                    ];
                    $logoPath = null;
                    foreach ($logoCandidates as $candidate) {
                        if (file_exists(public_path($candidate))) {
                            $logoPath = $candidate;
                            break;
                        }
                    }
                    // Fallback на логотип МАЖОР (как у SDP Market — везде их собственное лого
                    // пока не загружены реальные бренды).
                    $logoPath = $logoPath ?? 'brand/major-logo-wide.svg';
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
        </div>
    </section>
@endif
