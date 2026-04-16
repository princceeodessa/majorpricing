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

        const ariaLabel = favorited ? 'РЈР±СЂР°С‚СЊ РёР· РёР·Р±СЂР°РЅРЅРѕРіРѕ' : 'Р”РѕР±Р°РІРёС‚СЊ РІ РёР·Р±СЂР°РЅРЅРѕРµ';

        button.classList.toggle('is-active', favorited);
        button.setAttribute('aria-label', ariaLabel);
        button.setAttribute('title', ariaLabel);

        if (label) {
            label.textContent = favorited ? 'Р’ РёР·Р±СЂР°РЅРЅРѕРј' : 'Р’ РёР·Р±СЂР°РЅРЅРѕРµ';
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

const readIntWithFallback = (candidate, fallback) => {
    const parsed = Number.parseInt(`${candidate ?? ''}`, 10);

    return Number.isNaN(parsed) ? fallback : parsed;
};

const resolveQuantityConstraints = ({ min = 1, step = 1, max = 999 } = {}) => {
    const resolvedMin = clamp(readIntWithFallback(min, 1), 1, 999);
    const resolvedStep = Math.max(1, readIntWithFallback(step, resolvedMin));
    const resolvedMaxBase = clamp(readIntWithFallback(max, 999), resolvedMin, 999);
    const stepsToMax = Math.max(0, Math.floor((resolvedMaxBase - resolvedMin) / resolvedStep));
    const resolvedMax = resolvedMin + (stepsToMax * resolvedStep);

    return {
        min: resolvedMin,
        step: resolvedStep,
        max: Math.max(resolvedMin, resolvedMax),
    };
};

const readInputQuantityConstraints = (input) => {
    const quantityRoot = input instanceof HTMLInputElement
        ? input.closest('[data-qty]')
        : null;

    return resolveQuantityConstraints({
        min: quantityRoot instanceof HTMLElement
            ? quantityRoot.dataset.minQuantity ?? input?.getAttribute('min') ?? 1
            : input instanceof HTMLInputElement ? input.getAttribute('min') : 1,
        step: quantityRoot instanceof HTMLElement
            ? quantityRoot.dataset.stepQuantity ?? input?.getAttribute('step') ?? 1
            : input instanceof HTMLInputElement ? input.getAttribute('step') : 1,
        max: quantityRoot instanceof HTMLElement
            ? quantityRoot.dataset.maxQuantity ?? input?.getAttribute('max') ?? 999
            : input instanceof HTMLInputElement ? input.getAttribute('max') : 999,
    });
};

const readCartControlQuantityConstraints = (control) => resolveQuantityConstraints({
    min: control instanceof HTMLElement ? control.dataset.minQuantity : 1,
    step: control instanceof HTMLElement ? control.dataset.stepQuantity : 1,
    max: 999,
});

const normalizeQuantityByConstraints = (value, fallback, constraints) => {
    const parsed = Number.parseInt(`${value}`, 10);
    const fallbackValue = Number.isFinite(fallback) ? Math.trunc(fallback) : constraints.min;
    const baseValue = Number.isNaN(parsed) ? fallbackValue : parsed;
    const bounded = clamp(baseValue, constraints.min, constraints.max);
    const stepsFromMin = Math.max(0, Math.ceil((bounded - constraints.min) / constraints.step));
    const normalized = constraints.min + (stepsFromMin * constraints.step);

    return Math.max(constraints.min, Math.min(constraints.max, normalized));
};

const normalizeQty = (input, nextValue = input?.value ?? 1) => {
    const constraints = readInputQuantityConstraints(input);
    const normalized = normalizeQuantityByConstraints(nextValue, constraints.min, constraints);

    if (input instanceof HTMLInputElement) {
        input.value = `${normalized}`;
    }

    return normalized;
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

        const constraints = readInputQuantityConstraints(input);
        const delta = button.hasAttribute('data-qty-inc') ? constraints.step : -constraints.step;
        normalizeQty(input, readIntWithFallback(input.value, constraints.min) + delta);
    });

    document.addEventListener('change', (event) => {
        const input = event.target;

        if (!(input instanceof HTMLInputElement) || !input.matches('[data-qty-input]')) {
            return;
        }

        normalizeQty(input);
    });
};

const normalizeCartQuantity = (value, fallback = 1, constraints = resolveQuantityConstraints()) => {
    return normalizeQuantityByConstraints(value, fallback, constraints);
};

const syncSingleCartControl = (control, quantity) => {
    if (!(control instanceof HTMLElement)) {
        return;
    }

    const constraints = readCartControlQuantityConstraints(control);
    const safeQuantity = quantity > 0
        ? normalizeCartQuantity(quantity, constraints.min, constraints)
        : 0;

    control.dataset.quantity = `${safeQuantity}`;

    const addState = control.querySelector('[data-cart-add-state]');
    const qtyState = control.querySelector('[data-cart-qty-state]');
    const qtyValue = control.querySelector('[data-cart-quantity]');

    addState?.classList.toggle('hidden', safeQuantity > 0);
    qtyState?.classList.toggle('hidden', safeQuantity <= 0);

    if (qtyValue instanceof HTMLInputElement) {
        qtyValue.value = `${safeQuantity > 0 ? safeQuantity : constraints.min}`;

        return;
    }

    if (qtyValue instanceof HTMLElement) {
        qtyValue.textContent = `${safeQuantity}`;
    }
};

const syncCartControls = (productId, quantity, cartCount) => {
    document.querySelectorAll(`[data-cart-control][data-product-id="${productId}"]`).forEach((control) => {
        syncSingleCartControl(control, quantity);
    });

    document.querySelectorAll('[data-cart-count]').forEach((node) => {
        node.textContent = `${cartCount}`;
    });
};

const submitProductCardQuantity = async (control, nextQuantity) => {
    if (!(control instanceof HTMLElement) || control.dataset.loading === '1') {
        return;
    }

    const constraints = readCartControlQuantityConstraints(control);
    const currentQuantity = Math.max(0, readIntWithFallback(control.dataset.quantity, 0));
    let resolvedNextQuantity = Math.trunc(Number(nextQuantity) || 0);

    if (resolvedNextQuantity > 0) {
        resolvedNextQuantity = normalizeCartQuantity(
            resolvedNextQuantity,
            currentQuantity > 0 ? currentQuantity : constraints.min,
            constraints,
        );
    }

    if (resolvedNextQuantity > 0 && resolvedNextQuantity < constraints.min) {
        resolvedNextQuantity = constraints.min;
    }

    if (resolvedNextQuantity === currentQuantity) {
        syncSingleCartControl(control, currentQuantity);

        return;
    }

    const token = control.dataset.csrfToken;
    const body = new FormData();

    if (token) {
        body.append('_token', token);
    }

    let url = '';

    if (resolvedNextQuantity <= 0) {
        url = control.dataset.destroyUrl ?? '';
        body.append('_method', 'DELETE');
    } else if (currentQuantity <= 0) {
        url = control.dataset.storeUrl ?? '';
        body.append('quantity', `${resolvedNextQuantity}`);
    } else {
        url = control.dataset.updateUrl ?? '';
        body.append('_method', 'PATCH');
        body.append('quantity', `${resolvedNextQuantity}`);
    }

    if (!url) {
        return;
    }

    control.dataset.loading = '1';
    control.classList.add('is-loading');

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Cart control failed');
        }

        const payload = await response.json();

        syncCartControls(payload.productId, payload.quantity, payload.cartCount);
    } catch (error) {
        window.location.reload();
    } finally {
        control.dataset.loading = '0';
        control.classList.remove('is-loading');
    }
};

const setupProductCardCartControls = () => {
    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element
            ? event.target.closest('[data-cart-add], [data-cart-inc], [data-cart-dec]')
            : null;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const control = target.closest('[data-cart-control]');

        if (!(control instanceof HTMLElement)) {
            return;
        }

        event.preventDefault();

        const constraints = readCartControlQuantityConstraints(control);
        const quantityInput = control.querySelector('[data-cart-quantity-input]');
        const quantity = quantityInput instanceof HTMLInputElement
            ? normalizeCartQuantity(
                quantityInput.value,
                readIntWithFallback(control.dataset.quantity, constraints.min) || constraints.min,
                constraints,
            )
            : Math.max(0, readIntWithFallback(control.dataset.quantity, 0));
        let nextQuantity = quantity;

        if (target.hasAttribute('data-cart-add')) {
            nextQuantity = constraints.min;
        } else if (target.hasAttribute('data-cart-inc')) {
            nextQuantity = quantity + constraints.step;
        } else if (target.hasAttribute('data-cart-dec')) {
            nextQuantity = quantity - constraints.step;
        }

        void submitProductCardQuantity(control, nextQuantity);
    });

    document.addEventListener('change', (event) => {
        const input = event.target;

        if (!(input instanceof HTMLInputElement) || !input.matches('[data-cart-quantity-input]')) {
            return;
        }

        const control = input.closest('[data-cart-control]');

        if (!(control instanceof HTMLElement)) {
            return;
        }

        const constraints = readCartControlQuantityConstraints(control);
        const currentQuantity = Math.max(constraints.min, readIntWithFallback(control.dataset.quantity, constraints.min));
        const nextQuantity = normalizeCartQuantity(input.value, currentQuantity, constraints);

        input.value = `${nextQuantity}`;
        void submitProductCardQuantity(control, nextQuantity);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        const input = event.target;

        if (!(input instanceof HTMLInputElement) || !input.matches('[data-cart-quantity-input]')) {
            return;
        }

        event.preventDefault();
        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.blur();
    });
};

const setupProductGalleries = () => {
    document.querySelectorAll('[data-gallery]').forEach((galleryRoot) => {
        if (!(galleryRoot instanceof HTMLElement)) {
            return;
        }

        const mainImage = galleryRoot.querySelector('img[data-gallery-image]');
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

            if (mainImage instanceof HTMLImageElement && activeThumb.dataset.galleryImage) {
                mainImage.src = activeThumb.dataset.galleryImage;

                if (activeThumb.dataset.galleryAlt) {
                    mainImage.alt = activeThumb.dataset.galleryAlt;
                }
            }

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
            thumb.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                activateSlide(index);
            });
        });

        if (prev instanceof HTMLButtonElement) {
            prev.disabled = thumbs.length < 2;
            prev.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                activateSlide(Number(galleryRoot.dataset.galleryIndex || 0) - 1);
            });
        }

        if (next instanceof HTMLButtonElement) {
            next.disabled = thumbs.length < 2;
            next.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
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

const setupCategoryRails = () => {
    document.querySelectorAll('[data-category-rail]').forEach((railRoot) => {
        if (!(railRoot instanceof HTMLElement)) {
            return;
        }

        const viewport = railRoot.querySelector('[data-category-rail-track]');
        const prev = railRoot.querySelector('[data-category-rail-prev]');
        const next = railRoot.querySelector('[data-category-rail-next]');

        if (!(viewport instanceof HTMLElement)) {
            return;
        }

        const syncState = () => {
            const maxScroll = Math.max(0, viewport.scrollWidth - viewport.clientWidth);
            const atStart = viewport.scrollLeft <= 6;
            const atEnd = viewport.scrollLeft >= maxScroll - 6;
            const isStatic = maxScroll <= 6;

            railRoot.classList.toggle('is-static', isStatic);

            if (prev instanceof HTMLButtonElement) {
                prev.disabled = isStatic || atStart;
            }

            if (next instanceof HTMLButtonElement) {
                next.disabled = isStatic || atEnd;
            }
        };

        const scrollTrack = (direction) => {
            const distance = Math.max(280, viewport.clientWidth * 0.78);

            viewport.scrollBy({
                left: distance * direction,
                behavior: 'smooth',
            });
        };

        if (prev instanceof HTMLButtonElement) {
            prev.addEventListener('click', () => {
                scrollTrack(-1);
            });
        }

        if (next instanceof HTMLButtonElement) {
            next.addEventListener('click', () => {
                scrollTrack(1);
            });
        }

        viewport.addEventListener('scroll', syncState, { passive: true });
        window.addEventListener('resize', syncState);
        window.setTimeout(syncState, 80);
        syncState();
    });
};

const setupCatalogMenus = () => {
    const menus = Array.from(document.querySelectorAll('.catalog-header-catalog-menu'))
        .filter((menu) => menu instanceof HTMLElement);

    if (menus.length === 0) {
        return;
    }

    menus.forEach((menu) => {
        const dropdown = menu.querySelector('.catalog-header-catalog-dropdown');

        if (!(dropdown instanceof HTMLElement) || dropdown.querySelector('[data-catalog-menu-close]')) {
            return;
        }

        const allLink = dropdown.querySelector('.catalog-header-catalog-dropdown__all');
        const top = document.createElement('div');
        top.className = 'catalog-header-catalog-dropdown__top';

        if (allLink instanceof HTMLElement) {
            top.append(allLink);
        }

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'catalog-header-catalog-dropdown__close';
        closeButton.setAttribute('data-catalog-menu-close', '');
        closeButton.setAttribute('aria-label', 'Закрыть каталог');
        closeButton.innerHTML = `
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 7l10 10M17 7 7 17" />
            </svg>
        `;

        top.append(closeButton);
        dropdown.prepend(top);
    });

    document.addEventListener('click', (event) => {
        menus.forEach((menu) => {
            if (!(menu instanceof HTMLDetailsElement)) {
                return;
            }

            if (event.target instanceof Node && menu.contains(event.target)) {
                return;
            }

            menu.removeAttribute('open');
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        menus.forEach((menu) => {
            if (menu instanceof HTMLDetailsElement) {
                menu.removeAttribute('open');
            }
        });
    });

    document.addEventListener('click', (event) => {
        const closeButton = event.target instanceof Element
            ? event.target.closest('[data-catalog-menu-close]')
            : null;

        if (!(closeButton instanceof HTMLElement)) {
            return;
        }

        const menu = closeButton.closest('.catalog-header-catalog-menu');

        if (menu instanceof HTMLDetailsElement) {
            menu.removeAttribute('open');
        }
    });
};

const setupRepeatables = () => {
    document.querySelectorAll('[data-repeatable]').forEach((repeatableRoot) => {
        if (!(repeatableRoot instanceof HTMLElement)) {
            return;
        }

        const itemsRoot = repeatableRoot.querySelector('[data-repeatable-items]');
        const template = repeatableRoot.querySelector('template[data-repeatable-template]');

        if (!(itemsRoot instanceof HTMLElement) || !(template instanceof HTMLTemplateElement)) {
            return;
        }

        const syncRows = () => {
            const rows = Array.from(itemsRoot.querySelectorAll('[data-repeatable-row]'))
                .filter((row) => row instanceof HTMLElement);

            rows.forEach((row) => {
                const remove = row.querySelector('[data-repeatable-remove]');

                if (remove instanceof HTMLButtonElement) {
                    remove.disabled = rows.length === 1;
                }
            });
        };

        repeatableRoot.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target.closest('[data-repeatable-add], [data-repeatable-remove]') : null;

            if (!(target instanceof HTMLElement)) {
                return;
            }

            if (target.hasAttribute('data-repeatable-add')) {
                event.preventDefault();

                const fragment = template.content.cloneNode(true);
                itemsRoot.append(fragment);
                syncRows();
                const input = itemsRoot.querySelector('[data-repeatable-row]:last-child input');

                if (input instanceof HTMLInputElement) {
                    input.focus();
                }

                return;
            }

            if (target.hasAttribute('data-repeatable-remove')) {
                event.preventDefault();

                const row = target.closest('[data-repeatable-row]');
                const rows = Array.from(itemsRoot.querySelectorAll('[data-repeatable-row]'));

                if (!(row instanceof HTMLElement)) {
                    return;
                }

                if (rows.length === 1) {
                    const input = row.querySelector('input, textarea');

                    if (input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement) {
                        input.value = '';
                    }
                } else {
                    row.remove();
                }

                syncRows();
            }
        });

        syncRows();
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

        const showFallback = (label = 'РџРѕРєР°Р·Р°С‚СЊ РµС‰Рµ') => {
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
                        showFallback('РџРѕРєР°Р·Р°С‚СЊ РµС‰Рµ');
                    }

                    return;
                }

                stopFeed();
            } catch (error) {
                setLoadingState(false);
                showFallback('РџРѕРІС‚РѕСЂРёС‚СЊ Р·Р°РіСЂСѓР·РєСѓ');
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
            showFallback('РџРѕРєР°Р·Р°С‚СЊ РµС‰Рµ');
        }
    });
};

const setupSupportWidget = () => {
    const widget = document.querySelector('[data-support-widget]');

    if (!(widget instanceof HTMLElement)) {
        return;
    }

    const dialog = widget.querySelector('[role="dialog"]');
    const body = widget.querySelector('[data-support-widget-body]');
    const textarea = widget.querySelector('textarea[name="message"]');
    const openButtons = Array.from(document.querySelectorAll('[data-support-widget-open]'));
    const closeButtons = Array.from(widget.querySelectorAll('[data-support-widget-close]'));

    const syncBodyScroll = () => {
        if (body instanceof HTMLElement) {
            body.scrollTop = body.scrollHeight;
        }
    };

    const openWidget = () => {
        widget.classList.remove('hidden');
        syncBodyScroll();

        window.setTimeout(() => {
            if (textarea instanceof HTMLTextAreaElement) {
                textarea.focus();
            } else if (dialog instanceof HTMLElement) {
                dialog.focus();
            }
        }, 40);
    };

    const closeWidget = () => {
        widget.classList.add('hidden');
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', () => {
            openWidget();
        });
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            closeWidget();
        });
    });

    widget.addEventListener('click', (event) => {
        if (!(event.target instanceof HTMLElement)) {
            return;
        }

        if (event.target.hasAttribute('data-support-widget-close')) {
            closeWidget();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !widget.classList.contains('hidden')) {
            closeWidget();
        }
    });

    const form = widget.querySelector('form');
    const submitButton = form?.querySelector('button[type="submit"]');

    if (form instanceof HTMLFormElement) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (!(textarea instanceof HTMLTextAreaElement)) {
                return;
            }

            const message = textarea.value.trim();
            if (!message) {
                return;
            }

            if (submitButton instanceof HTMLButtonElement) {
                submitButton.disabled = true;
            }

            const formData = new FormData(form);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Support message failed');
                }

                const payload = await response.json();
                const thread = body instanceof HTMLElement ? body : null;

                if (thread) {
                    const wrapper = document.createElement('article');
                    wrapper.className = 'catalog-support-widget__message is-own';

                    wrapper.innerHTML = `
                        <div class="catalog-support-widget__bubble">
                            <p>${message.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</p>
                            <footer>
                                <span>Вы</span>
                                <time>${payload.sentAt || ''}</time>
                            </footer>
                        </div>
                    `;

                    const empty = thread.querySelector('.catalog-support-widget__empty');
                    empty?.remove();
                    thread.append(wrapper);
                    syncBodyScroll();
                }

                textarea.value = '';
            } catch (error) {
                pushToast('Ошибка', 'Сообщение не отправлено. Попробуйте еще раз.');
            } finally {
                if (submitButton instanceof HTMLButtonElement) {
                    submitButton.disabled = false;
                }
            }
        });
    }
    if (widget.dataset.supportWidgetDefaultOpen === '1') {
        openWidget();
    } else {
        syncBodyScroll();
    }
};

const formatRubles = (amount) => {
    const value = Number.parseFloat(`${amount ?? ''}`);

    if (!Number.isFinite(value)) {
        return 'По запросу';
    }

    return `${new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value)} ₽`;
};

const setupCartPaymentModes = () => {
    const scope = document.querySelector('[data-cart-payment-scope]');

    if (!(scope instanceof HTMLElement)) {
        return;
    }

    const paymentInputs = Array.from(scope.querySelectorAll('[data-cart-payment-method]'))
        .filter((input) => input instanceof HTMLInputElement);

    if (paymentInputs.length === 0) {
        return;
    }

    const sync = () => {
        const selected = paymentInputs.find((input) => input.checked);
        const paymentMethod = selected instanceof HTMLInputElement ? selected.value : 'bank_transfer';
        const isCash = paymentMethod === 'cash';

        scope.querySelectorAll('[data-cart-price-label]').forEach((node) => {
            if (node instanceof HTMLElement) {
                node.textContent = isCash ? 'Со скидкой' : 'Без скидки';
            }
        });

        scope.querySelectorAll('[data-cart-unit-price]').forEach((node) => {
            if (!(node instanceof HTMLElement)) {
                return;
            }

            const nextAmount = isCash ? node.dataset.discountPrice : node.dataset.basePrice;
            node.textContent = formatRubles(nextAmount);
            node.classList.toggle('catalog-cart-item__price-value--accent', isCash);
        });

        scope.querySelectorAll('[data-cart-line-total]').forEach((node) => {
            if (!(node instanceof HTMLElement)) {
                return;
            }

            const nextAmount = isCash ? node.dataset.discountTotal : node.dataset.baseTotal;
            node.textContent = `Итого: ${formatRubles(nextAmount)}`;
        });

        scope.querySelectorAll('[data-cart-compare-price]').forEach((node) => {
            if (node instanceof HTMLElement) {
                node.classList.toggle('hidden', !isCash);
            }
        });

        scope.querySelectorAll('[data-cart-hero-total], [data-cart-summary-total]').forEach((node) => {
            if (!(node instanceof HTMLElement)) {
                return;
            }

            const nextAmount = isCash ? node.dataset.discountTotal : node.dataset.baseTotal;
            node.textContent = formatRubles(nextAmount);
        });

        scope.querySelectorAll('.catalog-cart-item__payment-note').forEach((node) => {
            if (node instanceof HTMLElement) {
                node.textContent = isCash ? 'Наличный расчет' : 'Безналичный расчет';
            }
        });
    };

    paymentInputs.forEach((input) => input.addEventListener('change', sync));
    sync();
};

const setupCartInlineUpdates = () => {
    const scope = document.querySelector('[data-cart-payment-scope]');

    if (!(scope instanceof HTMLElement)) {
        return;
    }

    const getPaymentMethod = () => {
        const selected = scope.querySelector('[data-cart-payment-method]:checked');
        return selected instanceof HTMLInputElement ? selected.value : 'bank_transfer';
    };

    const updateSummary = (summary) => {
        if (!summary || typeof summary !== 'object') {
            return;
        }

        const setText = (selector, value) => {
            scope.querySelectorAll(selector).forEach((node) => {
                if (node instanceof HTMLElement) {
                    node.textContent = `${value}`;
                }
            });
        };

        if (summary.items_count !== undefined) {
            setText('[data-cart-items-count]', summary.items_count);
        }

        if (summary.total_quantity !== undefined) {
            setText('[data-cart-total-quantity]', summary.total_quantity);
        }

        if (summary.priced_items_count !== undefined) {
            setText('[data-cart-priced-count]', summary.priced_items_count);
        }

        if (summary.unpriced_items_count !== undefined) {
            setText('[data-cart-unpriced-count]', summary.unpriced_items_count);
        }

        scope.querySelectorAll('[data-cart-hero-total], [data-cart-summary-total]').forEach((node) => {
            if (!(node instanceof HTMLElement)) {
                return;
            }

            const nextAmount = summary.payment_method === 'cash'
                ? summary.discount_total_amount
                : summary.base_total_amount;
            node.textContent = formatRubles(nextAmount);
        });
    };

    const updateItemPricing = (itemRoot, payload) => {
        if (!(itemRoot instanceof HTMLElement) || !payload || typeof payload !== 'object') {
            return;
        }

        const paymentMethod = getPaymentMethod();
        const isCash = paymentMethod === 'cash';

        const unit = itemRoot.querySelector('[data-cart-unit-price]');
        const line = itemRoot.querySelector('[data-cart-line-total]');
        const compare = itemRoot.querySelector('[data-cart-compare-price]');

        if (unit instanceof HTMLElement) {
            if (payload.base_unit_amount !== undefined) {
                unit.dataset.basePrice = `${payload.base_unit_amount ?? ''}`;
            }
            if (payload.discount_unit_amount !== undefined) {
                unit.dataset.discountPrice = `${payload.discount_unit_amount ?? ''}`;
            }

            const nextAmount = isCash ? unit.dataset.discountPrice : unit.dataset.basePrice;
            unit.textContent = formatRubles(nextAmount);
            unit.classList.toggle('catalog-cart-item__price-value--accent', isCash);
        }

        if (line instanceof HTMLElement) {
            if (payload.base_line_amount !== undefined) {
                line.dataset.baseTotal = `${payload.base_line_amount ?? ''}`;
            }
            if (payload.discount_line_amount !== undefined) {
                line.dataset.discountTotal = `${payload.discount_line_amount ?? ''}`;
            }

            const nextAmount = isCash ? line.dataset.discountTotal : line.dataset.baseTotal;
            line.textContent = `Итого: ${formatRubles(nextAmount)}`;
        }

        if (compare instanceof HTMLElement) {
            compare.classList.toggle('hidden', !isCash);
        }

        itemRoot.querySelectorAll('[data-cart-price-label]').forEach((node) => {
            if (node instanceof HTMLElement) {
                node.textContent = isCash ? 'Со скидкой' : 'Без скидки';
            }
        });
    };

    const sendUpdate = async (form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const itemRoot = form.closest('[data-cart-item]');
        const formData = new FormData(form);
        formData.append('payment_method', getPaymentMethod());

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Cart update failed');
            }

            const payload = await response.json();

            if (payload.cartCount !== undefined) {
                document.querySelectorAll('[data-cart-count]').forEach((node) => {
                    node.textContent = `${payload.cartCount}`;
                });
            }

            if (payload.item && itemRoot) {
                updateItemPricing(itemRoot, payload.item);
            }

            if (payload.quantity !== undefined) {
                const qtyInput = form.querySelector('[data-qty-input]');

                if (qtyInput instanceof HTMLInputElement) {
                    const constraints = readInputQuantityConstraints(qtyInput);
                    const normalized = normalizeQuantityByConstraints(payload.quantity, constraints.min, constraints);
                    qtyInput.value = `${normalized}`;
                }
            }

            if (payload.summary) {
                updateSummary(payload.summary);
            }
        } catch (error) {
            pushToast('Ошибка', 'Не удалось обновить корзину. Проверьте соединение.');
        }
    };

    const timers = new WeakMap();

    const schedule = (form) => {
        const existing = timers.get(form);
        if (existing) {
            clearTimeout(existing);
        }
        const timeout = window.setTimeout(() => sendUpdate(form), 180);
        timers.set(form, timeout);
    };

    scope.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-cart-qty-form]')) {
            return;
        }

        event.preventDefault();
        schedule(form);
    });

    scope.addEventListener('click', (event) => {
        const button = event.target instanceof Element
            ? event.target.closest('[data-qty-dec], [data-qty-inc]')
            : null;
        if (!(button instanceof HTMLElement)) {
            return;
        }

        const form = button.closest('[data-cart-qty-form]');
        if (form instanceof HTMLFormElement) {
            schedule(form);
        }
    });

    scope.addEventListener('change', (event) => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || !input.matches('[data-qty-input]')) {
            return;
        }

        const form = input.closest('[data-cart-qty-form]');
        if (form instanceof HTMLFormElement) {
            schedule(form);
        }
    });
};
const escapeHtml = (value) => `${value ?? ''}`
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
const pushToast = (title, body) => {
    const toast = document.createElement('div');
    toast.className = 'catalog-toast';
    toast.setAttribute('data-toast', '');

    toast.innerHTML = `
        <div class="catalog-toast__content">
            <strong>${escapeHtml(title)}</strong>
            <p>${escapeHtml(body)}</p>
        </div>
        <button type="button" class="catalog-toast__close" data-toast-close aria-label="Закрыть">&times;</button>
    `;

    document.body.append(toast);

    window.setTimeout(() => {
        toast.remove();
    }, 6500);
};

const setupNotificationsPoller = () => {
    const meta = document.querySelector('meta[name="notifications-poll"]');
    if (!(meta instanceof HTMLMetaElement) || !meta.content) {
        return;
    }

    const url = meta.content;
    const messageKey = 'potolkovych:lastMessageAt';
    const orderKey = 'potolkovych:lastOrderAt';

    let lastMessageAt = window.localStorage.getItem(messageKey);
    let lastOrderAt = window.localStorage.getItem(orderKey);

    const poll = async (initial = false) => {
        const params = new URLSearchParams();
        if (lastMessageAt) {
            params.set('since_message', lastMessageAt);
        }
        if (lastOrderAt) {
            params.set('since_order', lastOrderAt);
        }

        try {
            const response = await fetch(`${url}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (!lastMessageAt && data.latest_message_at) {
                lastMessageAt = data.latest_message_at;
                window.localStorage.setItem(messageKey, lastMessageAt);
            } else if (data.message && data.message.created_at) {
                if (!initial) {
                    const countLabel = data.new_messages_count > 1 ? ` (${data.new_messages_count})` : '';
                    pushToast('Новое сообщение' + countLabel, data.message.preview || 'Откройте чат с менеджером.');
                }
                lastMessageAt = data.message.created_at;
                window.localStorage.setItem(messageKey, lastMessageAt);
            }

            if (!lastOrderAt && data.latest_order_at) {
                lastOrderAt = data.latest_order_at;
                window.localStorage.setItem(orderKey, lastOrderAt);
            } else if (data.order && data.order.updated_at) {
                if (!initial) {
                    const number = data.order.number ? ` ${data.order.number}` : '';
                    const status = data.order.status_label || data.order.status || '';
                    pushToast('Статус заказа' + number, status ? `Новый статус: ${status}` : 'Статус заказа обновлен.');
                }
                lastOrderAt = data.order.updated_at;
                window.localStorage.setItem(orderKey, lastOrderAt);
            }
        } catch (error) {
            // silent
        }
    };

    poll(true);
    window.setInterval(() => poll(false), 20000);
};
const setupToasts = () => {
    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element
            ? event.target.closest('[data-toast-close]')
            : null;

        if (!(button instanceof HTMLElement)) {
            return;
        }

        const toast = button.closest('[data-toast]');
        toast?.remove();
    });
};
const setupAccountRoleForms = () => {
    document.querySelectorAll('[data-account-role-form]').forEach((form) => {
        if (!(form instanceof HTMLElement)) {
            return;
        }

        const select = form.querySelector('[data-account-role-select]');
        const heading = form.parentElement?.querySelector('[data-account-role-heading]');
        const description = form.parentElement?.querySelector('[data-account-role-description]');
        const submit = form.querySelector('[data-account-role-submit]');
        const presetButtons = Array.from(form.parentElement?.querySelectorAll('[data-account-role-preset]') ?? [])
            .filter((button) => button instanceof HTMLButtonElement);

        if (!(select instanceof HTMLSelectElement)) {
            return;
        }

        const sync = () => {
            const role = select.value === 'manager' ? 'manager' : 'client';

            if (heading instanceof HTMLElement) {
                heading.textContent = role === 'manager'
                    ? (form.dataset.managerTitle || 'РЎРѕР·РґР°С‚СЊ РјРµРЅРµРґР¶РµСЂР°')
                    : (form.dataset.clientTitle || 'РЎРѕР·РґР°С‚СЊ РєР»РёРµРЅС‚Р°');
            }

            if (description instanceof HTMLElement) {
                description.textContent = role === 'manager'
                    ? (form.dataset.managerDescription || '')
                    : (form.dataset.clientDescription || '');
            }

            if (submit instanceof HTMLElement) {
                submit.textContent = role === 'manager'
                    ? (form.dataset.managerSubmit || 'РЎРѕР·РґР°С‚СЊ РјРµРЅРµРґР¶РµСЂР°')
                    : (form.dataset.clientSubmit || 'РЎРѕР·РґР°С‚СЊ РєР»РёРµРЅС‚Р°');
            }

            presetButtons.forEach((button) => {
                const isActive = button.dataset.accountRolePreset === role;

                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            form.querySelectorAll('[data-account-role-section]').forEach((section) => {
                if (!(section instanceof HTMLElement)) {
                    return;
                }

                const sectionRole = section.dataset.accountRoleSection ?? 'all';
                const isVisible = sectionRole === 'all' || sectionRole === role;

                section.hidden = !isVisible;

                section.querySelectorAll('input, select, textarea').forEach((field) => {
                    if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) {
                        field.disabled = !isVisible;
                    }
                });
            });
        };

        presetButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const role = button.dataset.accountRolePreset === 'manager' ? 'manager' : 'client';

                select.value = role;
                sync();
            });
        });

        select.addEventListener('change', sync);
        sync();
    });
};

document.addEventListener('DOMContentLoaded', () => {
    setupFavoriteToggles();
    setupPriceFilters();
    setupQuantityControls();
    setupProductCardCartControls();
    setupProductGalleries();
    setupHomeBanners();
    setupCatalogMenus();
    setupCategoryRails();
    setupRepeatables();
    setupAccountRoleForms();
    setupCartPaymentModes();
    setupCartInlineUpdates();
    setupInfiniteFeeds();
    setupSupportWidget();
    setupToasts();
    setupNotificationsPoller();
});
















