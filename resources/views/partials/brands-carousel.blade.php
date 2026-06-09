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
                    $logoPath = 'brand/logos/'.\Illuminate\Support\Str::slug($brand['name']).'.svg';
                    $logoExists = file_exists(public_path($logoPath));
                @endphp
                <a
                    href="{{ $brand['url'] }}"
                    class="brand-card {{ $isActive ? 'is-active' : '' }}"
                    aria-label="{{ $brand['name'] }} — {{ $brand['count'] }} товаров"
                    data-brand="{{ $brand['name'] }}"
                >
                    @if ($logoExists)
                        <img
                            src="{{ asset($logoPath) }}"
                            alt="{{ $brand['name'] }}"
                            class="brand-card__logo"
                            loading="lazy"
                            decoding="async"
                        >
                    @else
                        <span class="brand-card__placeholder">{{ $brand['name'] }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </section>
@endif
