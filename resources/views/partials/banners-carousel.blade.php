@php
    /** @var \Illuminate\Support\Collection $banners */
    $banners = $banners ?? collect();
@endphp

@if ($banners->isNotEmpty())
<style>
    .mj-bnr, .mj-bnr *, .mj-bnr *::before, .mj-bnr *::after { box-sizing: border-box; }
    .mj-bnr {
        display: block;
        margin: 14px 0 18px;
        padding: 0;
        overflow: hidden;
        max-width: 100%;
    }
    .mj-bnr__viewport {
        position: relative;
        display: flex;
        gap: 12px;
        overflow-x: auto;
        overflow-y: hidden;
        padding: 0 14px 4px;
        scroll-snap-type: x mandatory;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .mj-bnr__viewport::-webkit-scrollbar { display: none; }
    .mj-bnr__slide {
        flex: 0 0 calc(100% - 28px);
        max-width: calc(100% - 28px);
        aspect-ratio: 2 / 1;
        border-radius: 18px;
        overflow: hidden;
        position: relative;
        scroll-snap-align: center;
        background: #f6f7fa;
        text-decoration: none;
        color: inherit;
        box-shadow: 0 4px 14px -8px rgba(15, 23, 42, 0.18), 0 1px 3px rgba(15, 23, 42, 0.06);
        transition: transform .18s ease;
    }
    .mj-bnr__slide:active { transform: scale(0.985); }
    .mj-bnr__slide[data-static] { cursor: default; }
    .mj-bnr__img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .mj-bnr__placeholder {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 18px 22px;
        color: #ffffff;
        font: 800 clamp(18px, 4.5vw, 28px)/1.2 'IBM Plex Sans', system-ui, sans-serif;
        text-align: center;
        text-shadow: 0 1px 3px rgba(0, 0, 0, 0.22);
        letter-spacing: -0.01em;
    }
    .mj-bnr__placeholder::after {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse at top right, rgba(255, 255, 255, 0.18) 0%, transparent 60%),
            radial-gradient(ellipse at bottom left, rgba(0, 0, 0, 0.18) 0%, transparent 60%);
        pointer-events: none;
    }
    .mj-bnr__dots {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 10px 0 0;
    }
    .mj-bnr__dot {
        width: 6px;
        height: 6px;
        border-radius: 999px;
        background: #cbd5e1;
        transition: background-color .18s ease, transform .18s ease;
    }
    .mj-bnr__dot.is-active {
        background: #d60000;
        transform: scale(1.3);
    }

    @media (min-width: 768px) {
        .mj-bnr__viewport { padding: 0 24px 4px; gap: 16px; }
        .mj-bnr__slide { flex: 0 0 calc(100% - 48px); max-width: calc(100% - 48px); border-radius: 22px; }
    }
    @media (min-width: 1024px) {
        .mj-bnr__viewport { padding: 0 24px 4px; }
        .mj-bnr__slide { flex: 0 0 min(880px, calc(100% - 48px)); max-width: min(880px, calc(100% - 48px)); }
    }
    @media (prefers-reduced-motion: reduce) {
        .mj-bnr__viewport { scroll-behavior: auto; }
        .mj-bnr__slide { transition: none; }
        .mj-bnr__dot { transition: none; }
    }
</style>

<section class="mj-bnr" aria-label="Баннеры">
    <div class="mj-bnr__viewport" data-mj-bnr-viewport>
        @foreach ($banners as $banner)
            @php
                $img = $banner->imageUrl();
                $url = $banner->link_url;
                $tag = $url ? 'a' : 'div';
                $attrs = $url ? "href=\"{$url}\"" : 'data-static';
                $style = $banner->placeholder_color
                    ? 'background: '.e($banner->placeholder_color).';'
                    : '';
            @endphp
            <{{ $tag }}
                class="mj-bnr__slide"
                {!! $attrs !!}
                aria-label="{{ $banner->title }}"
                data-mj-bnr-slide
            >
                @if ($img)
                    <img src="{{ $img }}" alt="{{ $banner->title }}" class="mj-bnr__img" loading="lazy" decoding="async">
                @else
                    <div class="mj-bnr__placeholder" style="{{ $style }}">
                        <span>{{ $banner->placeholder_text ?? $banner->title }}</span>
                    </div>
                @endif
            </{{ $tag }}>
        @endforeach
    </div>
    @if ($banners->count() > 1)
        <div class="mj-bnr__dots" data-mj-bnr-dots role="tablist" aria-label="Перейти к баннеру">
            @foreach ($banners as $i => $banner)
                <button
                    type="button"
                    class="mj-bnr__dot {{ $i === 0 ? 'is-active' : '' }}"
                    data-mj-bnr-dot="{{ $i }}"
                    aria-label="Баннер {{ $i + 1 }}"
                ></button>
            @endforeach
        </div>
    @endif
</section>

<script>
    (function () {
        var viewport = document.querySelector('[data-mj-bnr-viewport]');
        var dots = document.querySelectorAll('[data-mj-bnr-dot]');
        if (!viewport || !dots.length) return;

        var slides = viewport.querySelectorAll('[data-mj-bnr-slide]');
        if (slides.length < 2) return;

        function updateActive() {
            var center = viewport.scrollLeft + viewport.clientWidth / 2;
            var bestIdx = 0;
            var bestDist = Infinity;
            slides.forEach(function (slide, i) {
                var rect = slide.getBoundingClientRect();
                var slideCenter = slide.offsetLeft + rect.width / 2;
                var dist = Math.abs(slideCenter - center);
                if (dist < bestDist) { bestDist = dist; bestIdx = i; }
            });
            dots.forEach(function (dot, i) {
                dot.classList.toggle('is-active', i === bestIdx);
            });
        }

        var ticking = false;
        viewport.addEventListener('scroll', function () {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(function () {
                updateActive();
                ticking = false;
            });
        }, { passive: true });

        dots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                var idx = parseInt(dot.getAttribute('data-mj-bnr-dot'), 10) || 0;
                var slide = slides[idx];
                if (!slide) return;
                viewport.scrollTo({
                    left: slide.offsetLeft - (viewport.clientWidth - slide.offsetWidth) / 2,
                    behavior: 'smooth',
                });
            });
        });

        updateActive();
    })();
</script>
@endif
