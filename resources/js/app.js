import './bootstrap';

const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

const syncFavoriteForms = (productId, favorited, favoritesCount, storeUrl, destroyUrl) => {
    document.querySelectorAll(`[data-favorite-form][data-product-id="${productId}"]`).forEach((form) => {
        const button = form.querySelector('[data-favorite-button]');
        const label = form.querySelector('[data-favorite-label]');
        let methodInput = form.querySelector('[data-favorite-method]');

        form.dataset.favorited = favorited ? '1' : '0';
        form.action = favorited ? destroyUrl : storeUrl;

        if (favorited && !methodInput) {
            methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'DELETE';
            methodInput.setAttribute('data-favorite-method', '');
            form.append(methodInput);
        }

        if (!favorited && methodInput) {
            methodInput.remove();
        }

        if (!button) {
            return;
        }

        const ariaLabel = favorited ? 'Убрать из избранного' : 'Добавить в избранное';

        button.classList.toggle('is-active', favorited);
        button.setAttribute('aria-label', ariaLabel);
        button.setAttribute('title', ariaLabel);

        if (label) {
            label.textContent = favorited ? 'В избранном' : 'В избранное';
        }
    });

    document.querySelectorAll('[data-favorites-count]').forEach((node) => {
        node.textContent = `${favoritesCount}`;
    });

    const favoritesPage = document.querySelector('[data-favorites-page]');

    if (favoritesPage && !favorited) {
        document.querySelectorAll(`[data-product-card][data-product-id="${productId}"]`).forEach((card) => {
            card.remove();
        });

        const grid = favoritesPage.querySelector('[data-favorites-grid]');
        const emptyState = favoritesPage.querySelector('[data-favorites-empty-state]');

        if (grid && emptyState && grid.querySelectorAll('[data-product-card]').length === 0) {
            grid.classList.add('hidden');
            emptyState.classList.remove('hidden');
        }
    }
};

const setupFavoriteToggles = () => {
    document.addEventListener('submit', async (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.matches('[data-favorite-form]')) {
            return;
        }

        event.preventDefault();

        const button = form.querySelector('[data-favorite-button]');

        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        button.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Favorite toggle failed');
            }

            const payload = await response.json();

            syncFavoriteForms(
                payload.productId,
                payload.favorited,
                payload.favoritesCount,
                payload.storeUrl,
                payload.destroyUrl,
            );
        } catch (error) {
            HTMLFormElement.prototype.submit.call(form);
        } finally {
            button.disabled = false;
        }
    });
};

const setupPriceFilters = () => {
    document.querySelectorAll('[data-price-filter]').forEach((filterRoot) => {
        const minBound = Number(filterRoot.dataset.minBound);
        const maxBound = Number(filterRoot.dataset.maxBound);
        const startRange = filterRoot.querySelector('[data-range-start]');
        const endRange = filterRoot.querySelector('[data-range-end]');
        const startInput = filterRoot.querySelector('[data-range-start-display]');
        const endInput = filterRoot.querySelector('[data-range-end-display]');
        const fill = filterRoot.querySelector('[data-range-fill]');

        if (
            !(startRange instanceof HTMLInputElement)
            || !(endRange instanceof HTMLInputElement)
            || !(startInput instanceof HTMLInputElement)
            || !(endInput instanceof HTMLInputElement)
            || !(fill instanceof HTMLElement)
            || Number.isNaN(minBound)
            || Number.isNaN(maxBound)
        ) {
            return;
        }

        const syncFill = (startValue, endValue) => {
            const range = maxBound - minBound || 1;
            const startPercent = ((startValue - minBound) / range) * 100;
            const endPercent = ((endValue - minBound) / range) * 100;

            fill.style.left = `${startPercent}%`;
            fill.style.width = `${Math.max(0, endPercent - startPercent)}%`;
        };

        const syncFromRange = () => {
            let startValue = clamp(Number(startRange.value), minBound, maxBound);
            let endValue = clamp(Number(endRange.value), minBound, maxBound);

            if (startValue > endValue) {
                [startValue, endValue] = [endValue, startValue];
            }

            startRange.value = `${startValue}`;
            endRange.value = `${endValue}`;
            startInput.value = startValue.toFixed(2);
            endInput.value = endValue.toFixed(2);
            syncFill(startValue, endValue);
        };

        const syncFromInput = () => {
            let startValue = clamp(Number(startInput.value || minBound), minBound, maxBound);
            let endValue = clamp(Number(endInput.value || maxBound), minBound, maxBound);

            if (startValue > endValue) {
                [startValue, endValue] = [endValue, startValue];
            }

            startRange.value = `${Math.round(startValue)}`;
            endRange.value = `${Math.round(endValue)}`;
            startInput.value = startValue.toFixed(2);
            endInput.value = endValue.toFixed(2);
            syncFill(startValue, endValue);
        };

        startRange.addEventListener('input', syncFromRange);
        endRange.addEventListener('input', syncFromRange);
        startInput.addEventListener('change', syncFromInput);
        endInput.addEventListener('change', syncFromInput);

        syncFromRange();
    });
};

const normalizeQty = (input, nextValue = input.value) => {
    const parsed = Number.parseInt(`${nextValue}`, 10);

    input.value = `${Number.isNaN(parsed) ? 1 : Math.max(1, parsed)}`;
};

const setupQuantityControls = () => {
    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element
            ? event.target.closest('[data-qty-dec], [data-qty-inc]')
            : null;

        if (!(button instanceof HTMLElement)) {
            return;
        }

        const qtyRoot = button.closest('[data-qty]');
        const input = qtyRoot?.querySelector('[data-qty-input]');

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        normalizeQty(input, Number(input.value) + (button.hasAttribute('data-qty-inc') ? 1 : -1));
    });

    document.addEventListener('change', (event) => {
        const input = event.target;

        if (!(input instanceof HTMLInputElement) || !input.matches('[data-qty-input]')) {
            return;
        }

        normalizeQty(input);
    });
};

const setupProductGalleries = () => {
    document.querySelectorAll('[data-gallery]').forEach((galleryRoot) => {
        if (!(galleryRoot instanceof HTMLElement)) {
            return;
        }

        const thumbs = Array.from(galleryRoot.querySelectorAll('[data-gallery-thumb]'))
            .filter((thumb) => thumb instanceof HTMLButtonElement);
        const prev = galleryRoot.querySelector('[data-gallery-prev]');
        const next = galleryRoot.querySelector('[data-gallery-next]');
        const chip = galleryRoot.querySelector('[data-gallery-chip]');
        const title = galleryRoot.querySelector('[data-gallery-title]');
        const meta = galleryRoot.querySelector('[data-gallery-meta]');

        if (thumbs.length === 0) {
            return;
        }

        const activateSlide = (nextIndex) => {
            const normalizedIndex = ((nextIndex % thumbs.length) + thumbs.length) % thumbs.length;
            const activeThumb = thumbs[normalizedIndex];

            galleryRoot.dataset.galleryIndex = `${normalizedIndex}`;

            thumbs.forEach((thumb, thumbIndex) => {
                const isActive = thumbIndex === normalizedIndex;

                thumb.classList.toggle('is-active', isActive);
                thumb.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            if (chip instanceof HTMLElement && activeThumb.dataset.galleryChip) {
                chip.textContent = activeThumb.dataset.galleryChip;
            }

            if (title instanceof HTMLElement && activeThumb.dataset.galleryTitle) {
                title.textContent = activeThumb.dataset.galleryTitle;
            }

            if (meta instanceof HTMLElement && activeThumb.dataset.galleryMeta) {
                meta.textContent = activeThumb.dataset.galleryMeta;
            }
        };

        thumbs.forEach((thumb, index) => {
            thumb.addEventListener('click', () => activateSlide(index));
        });

        if (prev instanceof HTMLButtonElement) {
            prev.disabled = thumbs.length < 2;
            prev.addEventListener('click', () => {
                activateSlide(Number(galleryRoot.dataset.galleryIndex || 0) - 1);
            });
        }

        if (next instanceof HTMLButtonElement) {
            next.disabled = thumbs.length < 2;
            next.addEventListener('click', () => {
                activateSlide(Number(galleryRoot.dataset.galleryIndex || 0) + 1);
            });
        }

        activateSlide(Number(galleryRoot.dataset.galleryIndex || 0));
    });
};

const setupHomeBanners = () => {
    document.querySelectorAll('[data-home-banners]').forEach((bannerRoot) => {
        if (!(bannerRoot instanceof HTMLElement)) {
            return;
        }

        const slides = Array.from(bannerRoot.querySelectorAll('[data-home-banner-slide]'))
            .filter((slide) => slide instanceof HTMLElement);
        const dots = Array.from(bannerRoot.querySelectorAll('[data-home-banner-dot]'))
            .filter((dot) => dot instanceof HTMLButtonElement);
        const prev = bannerRoot.querySelector('[data-home-banner-prev]');
        const next = bannerRoot.querySelector('[data-home-banner-next]');
        const progress = bannerRoot.querySelector('[data-home-banner-progress]');
        const interval = Math.max(3800, Number(bannerRoot.dataset.homeBannerInterval || 6800));

        if (slides.length === 0) {
            return;
        }

        let activeIndex = Math.max(0, slides.findIndex((slide) => slide.classList.contains('is-active')));
        let timerId = 0;
        let paused = false;

        const resetProgress = () => {
            if (!(progress instanceof HTMLElement)) {
                return;
            }

            progress.style.transition = 'none';
            progress.style.transform = 'scaleX(0)';
            progress.getBoundingClientRect();
        };

        const playProgress = () => {
            if (!(progress instanceof HTMLElement)) {
                return;
            }

            resetProgress();
            progress.style.transition = `transform ${interval}ms linear`;
            progress.style.transform = 'scaleX(1)';
        };

        const stopTimer = () => {
            if (timerId) {
                window.clearTimeout(timerId);
                timerId = 0;
            }

            resetProgress();
        };

        const queueNext = () => {
            if (paused || slides.length < 2) {
                return;
            }

            playProgress();
            timerId = window.setTimeout(() => {
                activateSlide(activeIndex + 1);
            }, interval);
        };

        const activateSlide = (nextIndex) => {
            activeIndex = ((nextIndex % slides.length) + slides.length) % slides.length;

            slides.forEach((slide, index) => {
                const isActive = index === activeIndex;

                slide.classList.toggle('is-active', isActive);
                slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            });

            dots.forEach((dot, index) => {
                const isActive = index === activeIndex;

                dot.classList.toggle('is-active', isActive);
                dot.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            stopTimer();
            queueNext();
        };

        dots.forEach((dot) => {
            dot.addEventListener('click', () => {
                activateSlide(Number(dot.dataset.homeBannerDot || 0));
            });
        });

        if (prev instanceof HTMLButtonElement) {
            prev.disabled = slides.length < 2;
            prev.addEventListener('click', () => {
                activateSlide(activeIndex - 1);
            });
        }

        if (next instanceof HTMLButtonElement) {
            next.disabled = slides.length < 2;
            next.addEventListener('click', () => {
                activateSlide(activeIndex + 1);
            });
        }

        bannerRoot.addEventListener('mouseenter', () => {
            paused = true;
            stopTimer();
        });

        bannerRoot.addEventListener('mouseleave', () => {
            paused = false;
            queueNext();
        });

        bannerRoot.addEventListener('focusin', () => {
            paused = true;
            stopTimer();
        });

        bannerRoot.addEventListener('focusout', (event) => {
            if (event.relatedTarget instanceof Node && bannerRoot.contains(event.relatedTarget)) {
                return;
            }

            paused = false;
            queueNext();
        });

        activateSlide(activeIndex);
    });
};

const setupInfiniteFeeds = () => {
    document.querySelectorAll('[data-infinite-feed]').forEach((feedRoot) => {
        if (!(feedRoot instanceof HTMLElement)) {
            return;
        }

        const grid = feedRoot.querySelector('[data-infinite-grid]');
        const trigger = feedRoot.querySelector('[data-infinite-trigger]');
        const loader = feedRoot.querySelector('[data-infinite-loader]');
        const button = feedRoot.querySelector('[data-infinite-button]');

        if (!(grid instanceof HTMLElement) || !(trigger instanceof HTMLElement)) {
            return;
        }

        let observer = null;

        const setLoadingState = (loading) => {
            feedRoot.dataset.loading = loading ? '1' : '0';

            if (loader instanceof HTMLElement) {
                loader.classList.toggle('hidden', !loading);
            }

            if (button instanceof HTMLButtonElement) {
                button.disabled = loading;
            }
        };

        const showFallback = (label = 'Показать еще') => {
            if (button instanceof HTMLButtonElement) {
                button.textContent = label;
                button.classList.remove('hidden');
            }
        };

        const hideFallback = () => {
            if (button instanceof HTMLButtonElement) {
                button.classList.add('hidden');
            }
        };

        const stopFeed = () => {
            feedRoot.dataset.nextPage = '';
            observer?.disconnect();
            trigger.remove();
        };

        const loadNextPage = async ({ manual = false } = {}) => {
            const nextPageUrl = feedRoot.dataset.nextPage;

            if (!nextPageUrl || feedRoot.dataset.loading === '1') {
                return;
            }

            observer?.unobserve(trigger);

            if (!manual) {
                hideFallback();
            }

            setLoadingState(true);

            try {
                const response = await fetch(nextPageUrl, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Infinite scroll failed');
                }

                const payload = await response.json();

                if (typeof payload.html === 'string' && payload.html.trim() !== '') {
                    grid.insertAdjacentHTML('beforeend', payload.html);
                }

                if (payload.nextPageUrl) {
                    feedRoot.dataset.nextPage = payload.nextPageUrl;
                    setLoadingState(false);

                    if (observer) {
                        requestAnimationFrame(() => observer.observe(trigger));
                    } else {
                        showFallback('Показать еще');
                    }

                    return;
                }

                stopFeed();
            } catch (error) {
                setLoadingState(false);
                showFallback('Повторить загрузку');
            }
        };

        setLoadingState(false);

        if (button instanceof HTMLButtonElement) {
            button.addEventListener('click', () => {
                loadNextPage({ manual: true });
            });
        }

        if ('IntersectionObserver' in window) {
            observer = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    loadNextPage();
                }
            }, {
                rootMargin: '700px 0px',
            });

            observer.observe(trigger);
        } else {
            showFallback('Показать еще');
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    setupFavoriteToggles();
    setupPriceFilters();
    setupQuantityControls();
    setupProductGalleries();
    setupHomeBanners();
    setupInfiniteFeeds();
});
