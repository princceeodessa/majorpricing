import './bootstrap';

const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
const CART_CONTROL_REQUEST_TIMEOUT_MS = 12000;

const parseJsonDataset = (value, fallback = null) => {
    if (typeof value !== 'string' || value.trim() === '') {
        return fallback;
    }

    try {
        return JSON.parse(value);
    } catch (error) {
        return fallback;
    }
};

const FAVORITE_SYNC_EVENT = 'catalog:favorite-sync';

const setFavoritesCount = (favoritesCount) => {
    const parsedCount = Number.parseInt(`${favoritesCount ?? ''}`, 10);
    if (!Number.isFinite(parsedCount)) {
        return;
    }

    document.querySelectorAll('[data-favorites-count]').forEach((node) => {
        if (node instanceof HTMLElement) {
            node.textContent = `${parsedCount}`;
        }
    });
};

const syncFavoritesPageState = (productId, favorited) => {
    const favoritesPage = document.querySelector('[data-favorites-page]');

    if (!(favoritesPage instanceof HTMLElement) || favorited) {
        return;
    }

    document.querySelectorAll(`[data-product-card][data-product-id="${productId}"]`).forEach((card) => {
        if (card instanceof HTMLElement) {
            card.remove();
        }
    });

    const grid = favoritesPage.querySelector('[data-favorites-grid]');
    const emptyState = favoritesPage.querySelector('[data-favorites-empty-state]');

    if (
        grid instanceof HTMLElement
        && emptyState instanceof HTMLElement
        && grid.querySelectorAll('[data-product-card]').length === 0
    ) {
        grid.classList.add('hidden');
        emptyState.classList.remove('hidden');
    }
};

const dispatchFavoriteSync = ({ productId, favorited, favoritesCount, storeUrl, destroyUrl }) => {
    document.dispatchEvent(new CustomEvent(FAVORITE_SYNC_EVENT, {
        detail: {
            productId,
            favorited,
            favoritesCount,
            storeUrl,
            destroyUrl,
        },
    }));
};

const syncFavoriteForms = (productId, favorited, favoritesCount, storeUrl, destroyUrl) => {
    document.querySelectorAll(`[data-favorite-form][data-product-id="${productId}"]`).forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        form.dataset.favorited = favorited ? '1' : '0';
        form.action = favorited ? destroyUrl : storeUrl;

        if (form.dataset.vueMountedFavorite === '1') {
            return;
        }

        const button = form.querySelector('[data-favorite-button]');
        const label = form.querySelector('[data-favorite-label]');
        let methodInput = form.querySelector('[data-favorite-method]');

        if (favorited && !methodInput) {
            methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'DELETE';
            methodInput.setAttribute('data-favorite-method', '');
            form.append(methodInput);
        }

        if (!favorited && methodInput instanceof HTMLElement) {
            methodInput.remove();
        }

        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        const ariaLabel = favorited ? 'Убрать из избранного' : 'Добавить в избранное';
        button.classList.toggle('is-active', favorited);
        button.setAttribute('aria-label', ariaLabel);
        button.setAttribute('title', ariaLabel);

        if (label instanceof HTMLElement) {
            label.textContent = favorited ? 'В избранном' : 'В избранное';
        }
    });

    setFavoritesCount(favoritesCount);
    syncFavoritesPageState(productId, favorited);
    dispatchFavoriteSync({
        productId,
        favorited,
        favoritesCount,
        storeUrl,
        destroyUrl,
    });
};

const setupFavoriteToggles = () => {
    document.addEventListener('submit', async (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.matches('[data-favorite-form]')) {
            return;
        }

        if (form.dataset.vueMountedFavorite === '1') {
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

const mountVueFavoriteToggles = (scope = document) => {
    if (typeof window === 'undefined' || !window.Vue?.createApp) {
        return;
    }

    const { createApp } = window.Vue;
    const roots = scope.querySelectorAll('[data-vue-favorite]');

    roots.forEach((root) => {
        if (!(root instanceof HTMLFormElement) || root.dataset.vueMountedFavorite === '1') {
            return;
        }

        const rawProps = parseJsonDataset(root.dataset.vueFavoriteProps, {}) ?? {};
        const parseBool = (value, fallback = false) => {
            if (typeof value === 'boolean') {
                return value;
            }

            if (typeof value === 'number') {
                return value !== 0;
            }

            if (typeof value === 'string') {
                const normalized = value.trim().toLowerCase();
                if (normalized === '1' || normalized === 'true') {
                    return true;
                }
                if (normalized === '0' || normalized === 'false') {
                    return false;
                }
            }

            return fallback;
        };

        const parseIntSafe = (value, fallback = 0) => {
            const parsed = Number.parseInt(`${value ?? ''}`, 10);
            return Number.isFinite(parsed) ? parsed : fallback;
        };

        const initialProductId = parseIntSafe(rawProps.productId ?? root.dataset.productId, 0);
        if (initialProductId <= 0) {
            return;
        }

        const initialStoreUrl = typeof rawProps.storeUrl === 'string' && rawProps.storeUrl.trim() !== ''
            ? rawProps.storeUrl
            : root.dataset.storeUrl ?? '';
        const initialDestroyUrl = typeof rawProps.destroyUrl === 'string' && rawProps.destroyUrl.trim() !== ''
            ? rawProps.destroyUrl
            : root.dataset.destroyUrl ?? '';

        if (initialStoreUrl === '' || initialDestroyUrl === '') {
            return;
        }

        const initialFavorited = parseBool(rawProps.favorited ?? root.dataset.favorited, false);
        const csrfToken = typeof rawProps.csrfToken === 'string' ? rawProps.csrfToken : '';
        const showLabel = parseBool(rawProps.showLabel, root.querySelector('[data-favorite-label]') instanceof HTMLElement);

        const app = createApp({
            data() {
                return {
                    productId: initialProductId,
                    favorited: initialFavorited,
                    storeUrl: initialStoreUrl,
                    destroyUrl: initialDestroyUrl,
                    csrfToken,
                    showLabel,
                    isLoading: false,
                };
            },
            computed: {
                actionUrl() {
                    return this.favorited ? this.destroyUrl : this.storeUrl;
                },
                ariaLabel() {
                    return this.favorited ? 'Убрать из избранного' : 'Добавить в избранное';
                },
                buttonLabel() {
                    return this.favorited ? 'В избранном' : 'В избранное';
                },
            },
            methods: {
                applyRootState() {
                    if (!(this.$el instanceof HTMLFormElement)) {
                        return;
                    }

                    this.$el.action = this.actionUrl;
                    this.$el.dataset.favorited = this.favorited ? '1' : '0';
                    this.$el.dataset.storeUrl = this.storeUrl;
                    this.$el.dataset.destroyUrl = this.destroyUrl;
                    this.$el.dataset.productId = `${this.productId}`;
                },
                handleFavoriteSync(event) {
                    const detail = event instanceof CustomEvent ? event.detail : null;
                    const productId = parseIntSafe(detail?.productId, 0);

                    if (productId !== this.productId) {
                        return;
                    }

                    this.favorited = parseBool(detail?.favorited, this.favorited);
                    if (typeof detail?.storeUrl === 'string' && detail.storeUrl.trim() !== '') {
                        this.storeUrl = detail.storeUrl;
                    }
                    if (typeof detail?.destroyUrl === 'string' && detail.destroyUrl.trim() !== '') {
                        this.destroyUrl = detail.destroyUrl;
                    }

                    this.applyRootState();
                },
                async submit() {
                    if (this.isLoading || this.actionUrl === '') {
                        return;
                    }

                    this.isLoading = true;
                    const body = new FormData();
                    if (this.csrfToken) {
                        body.append('_token', this.csrfToken);
                    }
                    if (this.favorited) {
                        body.append('_method', 'DELETE');
                    }

                    try {
                        const response = await fetch(this.actionUrl, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body,
                            credentials: 'same-origin',
                        });

                        if (response.status === 401 || response.status === 419) {
                            window.location.reload();
                            return;
                        }

                        if (!response.ok) {
                            throw new Error('Vue favorite toggle failed');
                        }

                        const payload = await response.json();
                        const nextFavorited = parseBool(payload?.favorited, !this.favorited);
                        const nextStoreUrl = typeof payload?.storeUrl === 'string' && payload.storeUrl.trim() !== ''
                            ? payload.storeUrl
                            : this.storeUrl;
                        const nextDestroyUrl = typeof payload?.destroyUrl === 'string' && payload.destroyUrl.trim() !== ''
                            ? payload.destroyUrl
                            : this.destroyUrl;
                        const nextFavoritesCount = parseIntSafe(payload?.favoritesCount, 0);

                        this.favorited = nextFavorited;
                        this.storeUrl = nextStoreUrl;
                        this.destroyUrl = nextDestroyUrl;
                        this.applyRootState();

                        syncFavoriteForms(
                            this.productId,
                            nextFavorited,
                            nextFavoritesCount,
                            nextStoreUrl,
                            nextDestroyUrl,
                        );
                    } catch (error) {
                        if (this.$el instanceof HTMLFormElement) {
                            HTMLFormElement.prototype.submit.call(this.$el);
                            return;
                        }

                        pushToast('Ошибка', 'Не удалось обновить избранное.');
                    } finally {
                        this.isLoading = false;
                    }
                },
                onSubmit(event) {
                    event.preventDefault();
                    this.submit();
                },
            },
            mounted() {
                this.applyRootState();
                document.addEventListener(FAVORITE_SYNC_EVENT, this.handleFavoriteSync);
                if (this.$el instanceof HTMLFormElement) {
                    this.$el.addEventListener('submit', this.onSubmit);
                }
            },
            beforeUnmount() {
                document.removeEventListener(FAVORITE_SYNC_EVENT, this.handleFavoriteSync);
                if (this.$el instanceof HTMLFormElement) {
                    this.$el.removeEventListener('submit', this.onSubmit);
                }
            },
            template: `
                <input v-if="csrfToken" type="hidden" name="_token" :value="csrfToken">
                <input v-if="favorited" type="hidden" name="_method" value="DELETE" data-favorite-method>
                <button
                    type="submit"
                    class="catalog-favorite-button"
                    :class="{ 'is-active': favorited }"
                    data-favorite-button
                    :aria-label="ariaLabel"
                    :title="ariaLabel"
                    :disabled="isLoading"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 21.35 10.55 20C5.4 15.24 2 12.09 2 8.25 2 5.1 4.42 2.75 7.5 2.75c1.74 0 3.41.81 4.5 2.09 1.09-1.28 2.76-2.09 4.5-2.09 3.08 0 5.5 2.35 5.5 5.5 0 3.84-3.4 6.99-8.55 11.76L12 21.35Z"/>
                    </svg>
                    <span v-if="showLabel" data-favorite-label>{{ buttonLabel }}</span>
                </button>
            `,
        });

        app.mount(root);
        root.dataset.vueMountedFavorite = '1';
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

const submitFallbackPost = (url, entries = []) => {
    if (
        typeof document === 'undefined'
        || typeof url !== 'string'
        || url.trim() === ''
    ) {
        return false;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    form.style.display = 'none';

    entries.forEach(([name, value]) => {
        if (typeof name !== 'string' || name === '' || typeof value !== 'string') {
            return;
        }

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.append(input);
    });

    document.body.append(form);
    form.submit();

    return true;
};

const syncCartLineSummary = (control, quantity) => {
    if (!(control instanceof HTMLElement)) {
        return;
    }

    const summary = control.querySelector('[data-cart-line-summary]');

    if (!(summary instanceof HTMLElement)) {
        return;
    }

    const amountNode = summary.querySelector('[data-cart-line-amount]');
    const unitAmount = Number.parseFloat(`${control.dataset.cartUnitAmount ?? ''}`.replace(',', '.'));
    const safeQuantity = Math.max(0, Math.trunc(Number(quantity) || 0));
    const shouldShow = Number.isFinite(unitAmount) && unitAmount >= 0 && safeQuantity > 0;

    summary.classList.toggle('hidden', !shouldShow);

    if (amountNode instanceof HTMLElement) {
        amountNode.textContent = shouldShow
            ? formatRubles(unitAmount * safeQuantity)
            : '';
    }
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
    syncCartLineSummary(control, safeQuantity);

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

    document.dispatchEvent(new CustomEvent('catalog:cart-sync', {
        detail: {
            productId,
            quantity,
            cartCount,
        },
    }));
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

    body.append('_response_format', 'json');

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

    const fallbackEntries = [];
    body.forEach((entryValue, entryName) => {
        if (typeof entryValue === 'string') {
            fallbackEntries.push([entryName, entryValue]);
        }
    });

    control.dataset.loading = '1';
    control.classList.add('is-loading');

    const requestController = typeof AbortController === 'function'
        ? new AbortController()
        : null;
    const requestTimeoutId = requestController
        ? window.setTimeout(() => requestController.abort(), CART_CONTROL_REQUEST_TIMEOUT_MS)
        : null;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
            credentials: 'same-origin',
            signal: requestController?.signal,
        });

        if (response.status === 401 || response.status === 419) {
            window.location.reload();

            return;
        }

        if (!response.ok) {
            if (submitFallbackPost(url, fallbackEntries)) {
                return;
            }

            throw new Error('Cart control failed');
        }

        const responseContentType = response.headers.get('content-type') ?? '';

        if (!responseContentType.includes('application/json')) {
            if (response.redirected && response.url) {
                window.location.assign(response.url);

                return;
            }

            if (submitFallbackPost(url, fallbackEntries)) {
                return;
            }

            window.location.reload();

            return;
        }

        const payload = await response.json();
        const payloadProductId = Number(payload?.productId);
        const payloadQuantity = Number(payload?.quantity);
        const payloadCartCount = Number(payload?.cartCount);

        if (
            !Number.isFinite(payloadProductId)
            || !Number.isFinite(payloadQuantity)
            || !Number.isFinite(payloadCartCount)
        ) {
            throw new Error('Cart control payload invalid');
        }

        syncCartControls(payloadProductId, payloadQuantity, payloadCartCount);
    } catch (error) {
        const isTimeoutAbort = typeof DOMException !== 'undefined'
            && error instanceof DOMException
            && error.name === 'AbortError';
        if (submitFallbackPost(url, fallbackEntries)) {
            return;
        }

        syncSingleCartControl(control, currentQuantity);
        pushToast(
            'Ошибка',
            isTimeoutAbort
                ? 'Сервер долго отвечает. Попробуйте еще раз.'
                : 'Не удалось обновить корзину. Проверьте соединение.',
        );
    } finally {
        if (requestTimeoutId !== null) {
            window.clearTimeout(requestTimeoutId);
        }

        control.dataset.loading = '0';
        control.classList.remove('is-loading');
    }
};

const setupProductCardCartControls = () => {
    document.querySelectorAll('[data-cart-control]').forEach((control) => {
        if (control instanceof HTMLElement) {
            syncSingleCartControl(control, readIntWithFallback(control.dataset.quantity, 0));
        }
    });

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
                const imageWrap = mainImage.closest('.catalog-product-card__image-wrap');
                if (imageWrap instanceof HTMLElement) {
                    imageWrap.classList.remove('is-wide', 'is-ultra-wide');
                }

                mainImage.src = activeThumb.dataset.galleryImage;
                setCatalogCardImageBackdrop(mainImage, activeThumb.dataset.galleryImage);

                if (activeThumb.dataset.galleryAlt) {
                    mainImage.alt = activeThumb.dataset.galleryAlt;
                }

                if (mainImage.complete && mainImage.naturalWidth > 0) {
                    applyCatalogCardImageMode(mainImage);
                    resolveCatalogCardSmartFit(mainImage);
                } else {
                    mainImage.addEventListener('load', () => {
                        applyCatalogCardImageMode(mainImage);
                        resolveCatalogCardSmartFit(mainImage);
                    }, { once: true });
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

        void pickBestInitialGalleryIndex(thumbs).then((bestIndex) => {
            if (!Number.isFinite(bestIndex)) {
                return;
            }

            if (bestIndex === Number(galleryRoot.dataset.galleryIndex || 0)) {
                return;
            }

            activateSlide(bestIndex);
        });
    });
};

const applyCatalogCardImageMode = (image) => {
    if (!(image instanceof HTMLImageElement)) {
        return;
    }

    const wrap = image.closest('.catalog-product-card__image-wrap');

    if (!(wrap instanceof HTMLElement)) {
        return;
    }

    setCatalogCardImageBackdrop(image);

    const naturalWidth = image.naturalWidth;
    const naturalHeight = image.naturalHeight;

    if (naturalWidth <= 0 || naturalHeight <= 0) {
        wrap.classList.remove('is-ultra-wide', 'is-wide');
        return;
    }

    const ratio = naturalWidth / naturalHeight;
    const isUltraWide = ratio >= 6.5;
    const isWide = !isUltraWide && ratio >= 3.4;

    wrap.classList.toggle('is-ultra-wide', isUltraWide);
    wrap.classList.toggle('is-wide', isWide);
};

const catalogCardImageAnalysisCache = new Map();

const escapeCssUrlToken = (value) => `${value}`
    .replace(/\\/g, '\\\\')
    .replace(/"/g, '\\"')
    .replace(/\n|\r/g, '');

const setCatalogCardImageBackdrop = (image, sourceUrl = '') => {
    if (!(image instanceof HTMLImageElement)) {
        return;
    }

    const wrap = image.closest('.catalog-product-card__image-wrap');
    if (!(wrap instanceof HTMLElement)) {
        return;
    }

    const imageUrl = `${sourceUrl || image.currentSrc || image.src || ''}`.trim();
    if (imageUrl === '') {
        wrap.style.removeProperty('--card-image-url');
        wrap.classList.remove('has-image-backdrop');
        return;
    }

    wrap.style.setProperty('--card-image-url', `url("${escapeCssUrlToken(imageUrl)}")`);
    wrap.classList.add('has-image-backdrop');
};

const clearCatalogCardSmartFit = (image) => {
    image.classList.remove('is-smart-fit');
    image.style.removeProperty('--card-img-shift-x');
    image.style.removeProperty('--card-img-shift-y');
    image.style.removeProperty('--card-img-scale');
};

const clearCatalogCardPreserveFit = (image) => {
    if (!(image instanceof HTMLImageElement)) {
        return;
    }

    image.classList.remove('is-preserve-fit');

    const wrap = image.closest('.catalog-product-card__image-wrap');
    if (wrap instanceof HTMLElement) {
        wrap.classList.remove('has-preserve-fit');
    }
};

const applyCatalogCardPreserveFit = (image) => {
    if (!(image instanceof HTMLImageElement)) {
        return;
    }

    clearCatalogCardSmartFit(image);
    clearCatalogCardFocusCover(image);

    image.classList.add('is-preserve-fit');

    const wrap = image.closest('.catalog-product-card__image-wrap');
    if (wrap instanceof HTMLElement) {
        wrap.classList.add('has-preserve-fit');
    }
};

const clearCatalogCardFocusCover = (image) => {
    if (!(image instanceof HTMLImageElement)) {
        return;
    }

    image.classList.remove('is-focus-cover');
    image.style.removeProperty('--card-img-focus-x');
    image.style.removeProperty('--card-img-focus-y');

    const wrap = image.closest('.catalog-product-card__image-wrap');
    if (wrap instanceof HTMLElement) {
        wrap.classList.remove('has-focus-cover');
    }
};

const applyCatalogCardFocusCoverValues = (image, focus) => {
    if (!(image instanceof HTMLImageElement) || !focus) {
        clearCatalogCardFocusCover(image);
        return;
    }

    clearCatalogCardSmartFit(image);

    const x = clamp(Number(focus.x ?? 50), 10, 90);
    const y = clamp(Number(focus.y ?? 50), 12, 88);

    image.classList.add('is-focus-cover');
    image.style.setProperty('--card-img-focus-x', `${x.toFixed(2)}%`);
    image.style.setProperty('--card-img-focus-y', `${y.toFixed(2)}%`);

    const wrap = image.closest('.catalog-product-card__image-wrap');
    if (wrap instanceof HTMLElement) {
        wrap.classList.add('has-focus-cover');
    }
};

const applyCatalogCardSmartFitValues = (image, fit) => {
    if (!fit) {
        clearCatalogCardSmartFit(image);
        return;
    }

    clearCatalogCardFocusCover(image);

    image.classList.add('is-smart-fit');
    image.style.setProperty('--card-img-shift-x', `${fit.shiftX.toFixed(2)}%`);
    image.style.setProperty('--card-img-shift-y', `${fit.shiftY.toFixed(2)}%`);
    image.style.setProperty('--card-img-scale', fit.scale.toFixed(3));
};

const colorDistanceRgb = (r1, g1, b1, r2, g2, b2) => (
    Math.abs(r1 - r2) + Math.abs(g1 - g2) + Math.abs(b1 - b2)
);

const computeCatalogCardImageAnalysis = (image, cacheKey) => {
    if (!(image instanceof HTMLImageElement)) {
        return null;
    }

    const src = cacheKey || image.currentSrc || image.src;
    if (!src) {
        return null;
    }

    if (catalogCardImageAnalysisCache.has(src)) {
        return catalogCardImageAnalysisCache.get(src);
    }

    const naturalWidth = image.naturalWidth || 0;
    const naturalHeight = image.naturalHeight || 0;
    if (naturalWidth <= 1 || naturalHeight <= 1) {
        catalogCardImageAnalysisCache.set(src, null);
        return null;
    }

    const sampleScale = Math.min(1, 260 / Math.max(naturalWidth, naturalHeight));
    const sampleWidth = Math.max(1, Math.round(naturalWidth * sampleScale));
    const sampleHeight = Math.max(1, Math.round(naturalHeight * sampleScale));

    const canvas = document.createElement('canvas');
    canvas.width = sampleWidth;
    canvas.height = sampleHeight;

    const context = canvas.getContext('2d', { willReadFrequently: true });
    if (!context) {
        catalogCardImageAnalysisCache.set(src, null);
        return null;
    }

    context.clearRect(0, 0, sampleWidth, sampleHeight);
    context.drawImage(image, 0, 0, sampleWidth, sampleHeight);

    let imageData;
    try {
        imageData = context.getImageData(0, 0, sampleWidth, sampleHeight).data;
    } catch (error) {
        catalogCardImageAnalysisCache.set(src, null);
        return null;
    }

    const cornerRead = (x, y) => {
        const idx = ((y * sampleWidth) + x) * 4;
        return [imageData[idx], imageData[idx + 1], imageData[idx + 2]];
    };

    const corners = [
        cornerRead(0, 0),
        cornerRead(sampleWidth - 1, 0),
        cornerRead(0, sampleHeight - 1),
        cornerRead(sampleWidth - 1, sampleHeight - 1),
    ];

    const bgR = Math.round((corners[0][0] + corners[1][0] + corners[2][0] + corners[3][0]) / 4);
    const bgG = Math.round((corners[0][1] + corners[1][1] + corners[2][1] + corners[3][1]) / 4);
    const bgB = Math.round((corners[0][2] + corners[1][2] + corners[2][2] + corners[3][2]) / 4);
    const bgThreshold = 36;

    let left = sampleWidth;
    let right = -1;
    let top = sampleHeight;
    let bottom = -1;
    let contentPixels = 0;

    for (let y = 0; y < sampleHeight; y++) {
        for (let x = 0; x < sampleWidth; x++) {
            const index = ((y * sampleWidth) + x) * 4;
            const r = imageData[index];
            const g = imageData[index + 1];
            const b = imageData[index + 2];
            const a = imageData[index + 3];

            if (a <= 10) {
                continue;
            }

            const foregroundByAlpha = a < 245;
            const foregroundByColor = colorDistanceRgb(r, g, b, bgR, bgG, bgB) > bgThreshold;

            if (!foregroundByAlpha && !foregroundByColor) {
                continue;
            }

            contentPixels++;
            if (x < left) left = x;
            if (x > right) right = x;
            if (y < top) top = y;
            if (y > bottom) bottom = y;
        }
    }

    if (contentPixels === 0 || right < left || bottom < top) {
        catalogCardImageAnalysisCache.set(src, null);
        return null;
    }

    const boxWidthPx = right - left + 1;
    const boxHeightPx = bottom - top + 1;
    const boxAreaPx = boxWidthPx * boxHeightPx;
    const fullAreaPx = sampleWidth * sampleHeight;
    const coverage = boxAreaPx / Math.max(1, fullAreaPx);
    const density = contentPixels / Math.max(1, boxAreaPx);

    const boxWidth = boxWidthPx / sampleWidth;
    const boxHeight = boxHeightPx / sampleHeight;
    const centerX = ((left + right + 1) / 2) / sampleWidth;
    const centerY = ((top + bottom + 1) / 2) / sampleHeight;

    const dominantSide = Math.max(boxWidth, boxHeight);
    const targetFill = coverage < 0.12 ? 0.9 : (coverage < 0.24 ? 0.86 : 0.82);
    const desiredScale = targetFill / Math.max(0.01, dominantSide);
    const safeScaleByBox = clamp(0.985 / Math.max(0.01, dominantSide), 1, 1.34);
    const candidateScale = clamp(desiredScale, 1, safeScaleByBox);
    const shiftX = clamp((0.5 - centerX) * 100, -14, 14);
    const shiftY = clamp((0.5 - centerY) * 100, -11, 11);

    const needsShift = Math.abs(shiftX) >= 1.35 || Math.abs(shiftY) >= 1.35;
    const needsScale = candidateScale >= 1.09 && coverage <= 0.4;
    const fit = (!needsShift && !needsScale)
        ? null
        : {
            shiftX,
            shiftY,
            scale: needsScale ? candidateScale : 1,
        };

    const score = clamp(coverage, 0, 1) * (0.58 + (0.42 * clamp(density, 0, 1)));

    const analysis = {
        fit,
        coverage,
        density,
        score,
        boxWidth,
        boxHeight,
        centerX,
        centerY,
        boxAspect: boxWidthPx / Math.max(1, boxHeightPx),
    };

    catalogCardImageAnalysisCache.set(src, analysis);
    return analysis;
};

const loadCatalogCardImageAnalysis = (url) => {
    if (!url) {
        return Promise.resolve(null);
    }

    if (catalogCardImageAnalysisCache.has(url)) {
        return Promise.resolve(catalogCardImageAnalysisCache.get(url));
    }

    return new Promise((resolve) => {
        const image = new Image();
        image.decoding = 'async';
        image.loading = 'eager';
        image.onload = () => {
            resolve(computeCatalogCardImageAnalysis(image, url));
        };
        image.onerror = () => {
            catalogCardImageAnalysisCache.set(url, null);
            resolve(null);
        };
        image.src = url;
    });
};

const MAX_INITIAL_GALLERY_CANDIDATES = 4;

const pickBestInitialGalleryIndex = async (thumbs) => {
    if (thumbs.length < 2) {
        return 0;
    }

    const candidates = thumbs.slice(0, MAX_INITIAL_GALLERY_CANDIDATES);
    const firstUrl = candidates[0]?.dataset.galleryImage || '';
    const firstAnalysis = await loadCatalogCardImageAnalysis(firstUrl);
    const firstScore = firstAnalysis?.score ?? 0;
    let bestIndex = 0;
    let bestScore = firstScore;

    if (firstScore >= 0.56) {
        return 0;
    }

    for (let index = 1; index < candidates.length; index++) {
        const candidateUrl = candidates[index]?.dataset.galleryImage || '';
        // Sequential analysis keeps initial page load lighter on mobile.
        const analysis = await loadCatalogCardImageAnalysis(candidateUrl);
        const score = analysis?.score ?? 0;
        if (score > bestScore) {
            bestScore = score;
            bestIndex = index;
        }
    }

    if (bestIndex === 0) {
        return 0;
    }

    if ((bestScore - firstScore) < 0.045) {
        return 0;
    }

    return bestIndex;
};

const resolveCatalogCardSmartFit = (image) => {
    if (!(image instanceof HTMLImageElement)) {
        return;
    }

    clearCatalogCardSmartFit(image);
    clearCatalogCardFocusCover(image);
    clearCatalogCardPreserveFit(image);

    if (!image.complete || image.naturalWidth <= 1 || image.naturalHeight <= 1) {
        return;
    }

    const analysis = computeCatalogCardImageAnalysis(image);
    if (!analysis) {
        return;
    }

    const coverage = Number(analysis.coverage ?? 1);
    const density = Number(analysis.density ?? 1);
    const boxWidth = Number(analysis.boxWidth ?? 1);
    const boxHeight = Number(analysis.boxHeight ?? 1);
    const centerX = Number(analysis.centerX ?? 0.5);
    const centerY = Number(analysis.centerY ?? 0.5);
    const dominantSide = Math.max(boxWidth, boxHeight);

    let nextFit = analysis.fit ? {
        shiftX: Number(analysis.fit.shiftX ?? 0),
        shiftY: Number(analysis.fit.shiftY ?? 0),
        scale: Number(analysis.fit.scale ?? 1),
    } : null;

    if (!nextFit && coverage < 0.29) {
        nextFit = {
            shiftX: clamp((0.5 - centerX) * 6.8, -5.4, 5.4),
            shiftY: clamp((0.5 - centerY) * 5.8, -4.8, 4.8),
            scale: clamp(0.9 / Math.max(0.01, dominantSide), 1.05, 1.32),
        };
    }

    if (!nextFit) {
        return;
    }

    const scaleCap = coverage < 0.19 ? 1.42 : (coverage < 0.32 ? 1.36 : 1.3);
    const tunedScale = coverage <= 0.64
        ? clamp(nextFit.scale, 1.02, scaleCap)
        : 1;

    const allowShift = coverage <= 0.44 && density >= 0.07;
    const tunedShiftX = allowShift ? clamp(nextFit.shiftX * 0.56, -7.2, 7.2) : 0;
    const tunedShiftY = allowShift ? clamp(nextFit.shiftY * 0.48, -6.2, 6.2) : 0;

    const barelyChanged = tunedScale < 1.04
        && Math.abs(tunedShiftX) < 0.75
        && Math.abs(tunedShiftY) < 0.75;

    if (barelyChanged) {
        return;
    }

    applyCatalogCardSmartFitValues(image, {
        shiftX: tunedShiftX,
        shiftY: tunedShiftY,
        scale: tunedScale,
    });
};

const setupCatalogCardImageModes = () => {
    document.querySelectorAll('.catalog-product-card__image').forEach((node) => {
        if (!(node instanceof HTMLImageElement)) {
            return;
        }

        if (node.complete) {
            applyCatalogCardImageMode(node);
            resolveCatalogCardSmartFit(node);
        } else {
            node.addEventListener('load', () => {
                applyCatalogCardImageMode(node);
                resolveCatalogCardSmartFit(node);
            }, { once: true });
        }
    });
};

const mountVueCatalogGalleries = (scope = document) => {
    if (typeof window === 'undefined' || !window.Vue?.createApp) {
        return;
    }

    const { createApp } = window.Vue;
    const roots = scope.querySelectorAll('[data-vue-gallery]');

    roots.forEach((root) => {
        if (!(root instanceof HTMLElement) || root.dataset.vueMountedGallery === '1') {
            return;
        }

        const rawProps = parseJsonDataset(root.dataset.vueGalleryProps, {}) ?? {};
        const images = Array.isArray(rawProps.images)
            ? rawProps.images.filter((url) => typeof url === 'string' && url.trim() !== '')
            : [];
        const fallbackImageUrl = typeof rawProps.fallbackImageUrl === 'string'
            ? rawProps.fallbackImageUrl
            : '';
        const safeImages = images.length > 0
            ? images
            : (fallbackImageUrl ? [fallbackImageUrl] : []);

        if (safeImages.length === 0) {
            return;
        }

        const productUrl = typeof rawProps.productUrl === 'string' && rawProps.productUrl.trim() !== ''
            ? rawProps.productUrl
            : '#';
        const title = typeof rawProps.title === 'string'
            ? rawProps.title
            : '';

        const app = createApp({
            data() {
                return {
                    images: safeImages,
                    fallbackImageUrl,
                    productUrl,
                    title,
                    currentIndex: 0,
                    touchStartX: null,
                    touchStartY: null,
                };
            },
            computed: {
                currentImage() {
                    return this.images[this.currentIndex] ?? this.fallbackImageUrl;
                },
                hasControls() {
                    return this.images.length > 1;
                },
            },
            methods: {
                normalizeIndex(index) {
                    if (this.images.length === 0) {
                        return 0;
                    }

                    return ((index % this.images.length) + this.images.length) % this.images.length;
                },
                activate(index) {
                    this.currentIndex = this.normalizeIndex(index);
                    this.$nextTick(() => {
                        this.applyImageFit();
                    });
                },
                activatePrev() {
                    this.activate(this.currentIndex - 1);
                },
                activateNext() {
                    this.activate(this.currentIndex + 1);
                },
                onTouchStart(event) {
                    const point = event?.changedTouches?.[0];
                    if (!point) {
                        return;
                    }

                    this.touchStartX = point.clientX;
                    this.touchStartY = point.clientY;
                },
                onTouchEnd(event) {
                    if (!this.hasControls || this.touchStartX === null || this.touchStartY === null) {
                        this.touchStartX = null;
                        this.touchStartY = null;
                        return;
                    }

                    const point = event?.changedTouches?.[0];
                    if (!point) {
                        this.touchStartX = null;
                        this.touchStartY = null;
                        return;
                    }

                    const deltaX = point.clientX - this.touchStartX;
                    const deltaY = point.clientY - this.touchStartY;

                    this.touchStartX = null;
                    this.touchStartY = null;

                    if (Math.abs(deltaX) < 26 || Math.abs(deltaX) <= (Math.abs(deltaY) * 1.15)) {
                        return;
                    }

                    if (deltaX > 0) {
                        this.activatePrev();
                        return;
                    }

                    this.activateNext();
                },
                applyImageFit() {
                    const image = this.$refs.mainImage;

                    if (!(image instanceof HTMLImageElement)) {
                        return;
                    }

                    const wrap = image.closest('.catalog-product-card__image-wrap');
                    if (wrap instanceof HTMLElement) {
                        wrap.classList.remove('is-wide', 'is-ultra-wide');
                    }

                    if (image.complete && image.naturalWidth > 0) {
                        applyCatalogCardImageMode(image);
                        resolveCatalogCardSmartFit(image);
                    }
                },
                onImageLoad() {
                    this.applyImageFit();
                },
                onImageError(event) {
                    const target = event.target;

                    if (!(target instanceof HTMLImageElement)) {
                        return;
                    }

                    if (this.fallbackImageUrl && target.src !== this.fallbackImageUrl) {
                        target.src = this.fallbackImageUrl;
                        setCatalogCardImageBackdrop(target, this.fallbackImageUrl);
                    }
                },
            },
            mounted() {
                this.$nextTick(() => {
                    this.applyImageFit();
                });
            },
            template: `
                <div>
                    <a :href="productUrl" class="catalog-product-card__visual-link">
                        <div
                            class="catalog-product-card__image-wrap"
                            @touchstart.passive="onTouchStart"
                            @touchend.passive="onTouchEnd"
                        >
                            <img
                                ref="mainImage"
                                :src="currentImage"
                                :alt="title"
                                class="catalog-product-card__image"
                                loading="lazy"
                                decoding="async"
                                @load="onImageLoad"
                                @error="onImageError"
                            >
                        </div>
                    </a>

                    <template v-if="hasControls">
                        <button
                            type="button"
                            class="catalog-product-card__gallery-arrow is-prev"
                            aria-label="Предыдущее фото"
                            @click.prevent.stop="activatePrev"
                        >
                            &#8249;
                        </button>
                        <button
                            type="button"
                            class="catalog-product-card__gallery-arrow is-next"
                            aria-label="Следующее фото"
                            @click.prevent.stop="activateNext"
                        >
                            &#8250;
                        </button>

                        <div class="catalog-product-card__gallery-dots">
                            <button
                                v-for="(imageUrl, index) in images"
                                :key="imageUrl + '-' + index"
                                type="button"
                                class="catalog-product-card__gallery-dot"
                                :class="{ 'is-active': index === currentIndex }"
                                :aria-label="'Фото ' + (index + 1)"
                                :aria-pressed="index === currentIndex ? 'true' : 'false'"
                                @click.prevent.stop="activate(index)"
                            ></button>
                        </div>
                    </template>
                </div>
            `,
        });

        app.mount(root);
        root.dataset.vueMountedGallery = '1';
    });
};

const mountVueCatalogCartControls = (scope = document) => {
    if (typeof window === 'undefined' || !window.Vue?.createApp) {
        return;
    }

    const { createApp } = window.Vue;
    const roots = scope.querySelectorAll('[data-vue-cart-control]');

    roots.forEach((root) => {
        if (!(root instanceof HTMLElement) || root.dataset.vueMountedCart === '1') {
            return;
        }

        const rawProps = parseJsonDataset(root.dataset.vueCartProps, {}) ?? {};
        const constraints = resolveQuantityConstraints({
            min: rawProps.minQuantity,
            step: rawProps.stepQuantity,
            max: rawProps.maxQuantity,
        });

        const initialQuantity = Number(rawProps.quantity) > 0
            ? normalizeCartQuantity(rawProps.quantity, constraints.min, constraints)
            : 0;

        const app = createApp({
            data() {
                return {
                    productId: readIntWithFallback(rawProps.productId, 0),
                    quantity: initialQuantity,
                    minQuantity: constraints.min,
                    stepQuantity: constraints.step,
                    maxQuantity: constraints.max,
                    storeUrl: typeof rawProps.storeUrl === 'string' ? rawProps.storeUrl : '',
                    updateUrl: typeof rawProps.updateUrl === 'string' ? rawProps.updateUrl : '',
                    destroyUrl: typeof rawProps.destroyUrl === 'string' ? rawProps.destroyUrl : '',
                    csrfToken: typeof rawProps.csrfToken === 'string' ? rawProps.csrfToken : '',
                    unitsInPackageSummary: typeof rawProps.unitsInPackageSummary === 'string'
                        ? rawProps.unitsInPackageSummary
                        : '',
                    colorName: typeof rawProps.colorName === 'string' ? rawProps.colorName : '',
                    draftQuantity: `${initialQuantity > 0 ? initialQuantity : constraints.min}`,
                    isLoading: false,
                    pendingQuantity: null,
                };
            },
            computed: {
                hasQuantity() {
                    return this.quantity > 0;
                },
            },
            methods: {
                quantityConstraints() {
                    return {
                        min: this.minQuantity,
                        step: this.stepQuantity,
                        max: this.maxQuantity,
                    };
                },
                normalize(value, fallback) {
                    return normalizeCartQuantity(value, fallback, this.quantityConstraints());
                },
                resolveActionBaseQuantity() {
                    const pending = Math.max(0, Math.trunc(Number(this.pendingQuantity) || 0));
                    if (this.isLoading && pending > 0) {
                        return pending;
                    }

                    return this.quantity > 0 ? this.quantity : this.minQuantity;
                },
                syncLocalQuantity(quantity) {
                    const safeQuantity = quantity > 0
                        ? this.normalize(quantity, this.minQuantity)
                        : 0;

                    this.quantity = safeQuantity;
                    this.draftQuantity = `${safeQuantity > 0 ? safeQuantity : this.minQuantity}`;
                },
                addToCart() {
                    this.submit(this.minQuantity);
                },
                increment() {
                    const base = this.resolveActionBaseQuantity();
                    this.submit(base + this.stepQuantity);
                },
                decrement() {
                    const base = this.resolveActionBaseQuantity();
                    this.submit(base - this.stepQuantity);
                },
                onQuantityChange() {
                    const pending = Math.max(0, Math.trunc(Number(this.pendingQuantity) || 0));
                    const fallback = (this.isLoading && pending > 0)
                        ? pending
                        : (this.quantity > 0 ? this.quantity : this.minQuantity);
                    const normalized = this.normalize(this.draftQuantity, fallback);
                    this.draftQuantity = `${normalized}`;
                    this.submit(normalized);
                },
                handleCartSync(event) {
                    const detail = event instanceof CustomEvent ? event.detail : null;
                    const productId = Number(detail?.productId);

                    if (productId !== this.productId) {
                        return;
                    }

                    const quantity = Number(detail?.quantity);
                    this.syncLocalQuantity(Number.isFinite(quantity) ? quantity : 0);
                },
                async submit(nextQuantity) {
                    const quantityConstraints = this.quantityConstraints();
                    const currentQuantity = Math.max(0, this.quantity);
                    const pendingBaseQuantity = Math.max(0, Math.trunc(Number(this.pendingQuantity) || 0));
                    const normalizationFallback = pendingBaseQuantity > 0
                        ? pendingBaseQuantity
                        : (currentQuantity > 0 ? currentQuantity : quantityConstraints.min);
                    let resolvedNextQuantity = Math.trunc(Number(nextQuantity) || 0);

                    if (resolvedNextQuantity > 0) {
                        resolvedNextQuantity = normalizeCartQuantity(
                            resolvedNextQuantity,
                            normalizationFallback,
                            quantityConstraints,
                        );
                    }

                    if (resolvedNextQuantity > 0 && resolvedNextQuantity < quantityConstraints.min) {
                        resolvedNextQuantity = quantityConstraints.min;
                    }

                    if (this.isLoading) {
                        this.pendingQuantity = resolvedNextQuantity;
                        this.draftQuantity = `${resolvedNextQuantity > 0 ? resolvedNextQuantity : this.minQuantity}`;
                        return;
                    }

                    if (resolvedNextQuantity === currentQuantity) {
                        this.syncLocalQuantity(currentQuantity);
                        return;
                    }

                    const body = new FormData();

                    if (this.csrfToken) {
                        body.append('_token', this.csrfToken);
                    }

                    body.append('_response_format', 'json');

                    let url = '';

                    if (resolvedNextQuantity <= 0) {
                        url = this.destroyUrl;
                        body.append('_method', 'DELETE');
                    } else if (currentQuantity <= 0) {
                        url = this.storeUrl;
                        body.append('quantity', `${resolvedNextQuantity}`);
                    } else {
                        url = this.updateUrl;
                        body.append('_method', 'PATCH');
                        body.append('quantity', `${resolvedNextQuantity}`);
                    }

                    if (!url) {
                        return;
                    }

                    const fallbackEntries = [];
                    body.forEach((entryValue, entryName) => {
                        if (typeof entryValue === 'string') {
                            fallbackEntries.push([entryName, entryValue]);
                        }
                    });

                    this.isLoading = true;
                    this.pendingQuantity = null;
                    if (this.$el instanceof HTMLElement) {
                        this.$el.classList.add('is-loading');
                        this.$el.setAttribute('aria-busy', 'true');
                    }

                    const requestController = typeof AbortController === 'function'
                        ? new AbortController()
                        : null;
                    const requestTimeoutId = requestController
                        ? window.setTimeout(() => requestController.abort(), CART_CONTROL_REQUEST_TIMEOUT_MS)
                        : null;

                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body,
                            credentials: 'same-origin',
                            signal: requestController?.signal,
                        });

                        if (response.status === 401 || response.status === 419) {
                            window.location.reload();
                            return;
                        }

                        if (!response.ok) {
                            if (submitFallbackPost(url, fallbackEntries)) {
                                return;
                            }

                            throw new Error('Vue cart control failed');
                        }

                        const responseContentType = response.headers.get('content-type') ?? '';

                        if (!responseContentType.includes('application/json')) {
                            if (response.redirected && response.url) {
                                window.location.assign(response.url);
                                return;
                            }

                            if (submitFallbackPost(url, fallbackEntries)) {
                                return;
                            }

                            window.location.reload();
                            return;
                        }

                        const payload = await response.json();
                        const payloadProductId = Number(payload?.productId);
                        const payloadQuantity = Number(payload?.quantity);
                        const payloadCartCount = Number(payload?.cartCount);

                        if (
                            !Number.isFinite(payloadProductId)
                            || !Number.isFinite(payloadQuantity)
                            || !Number.isFinite(payloadCartCount)
                        ) {
                            throw new Error('Vue cart payload invalid');
                        }

                        syncCartControls(payloadProductId, payloadQuantity, payloadCartCount);
                    } catch (error) {
                        const isTimeoutAbort = typeof DOMException !== 'undefined'
                            && error instanceof DOMException
                            && error.name === 'AbortError';
                        if (submitFallbackPost(url, fallbackEntries)) {
                            return;
                        }

                        this.syncLocalQuantity(currentQuantity);

                        pushToast(
                            'Ошибка',
                            isTimeoutAbort
                                ? 'Сервер долго отвечает. Попробуйте еще раз.'
                                : 'Не удалось обновить корзину. Проверьте соединение.',
                        );
                    } finally {
                        if (requestTimeoutId !== null) {
                            window.clearTimeout(requestTimeoutId);
                        }

                        this.isLoading = false;
                        if (this.$el instanceof HTMLElement) {
                            this.$el.classList.remove('is-loading');
                            this.$el.removeAttribute('aria-busy');
                        }

                        const queuedQuantity = Number(this.pendingQuantity);
                        this.pendingQuantity = null;

                        if (Number.isFinite(queuedQuantity) && Math.trunc(queuedQuantity) !== Math.max(0, this.quantity)) {
                            this.submit(queuedQuantity);
                        }
                    }
                },
            },
            mounted() {
                document.addEventListener('catalog:cart-sync', this.handleCartSync);
                this.syncLocalQuantity(this.quantity);
            },
            beforeUnmount() {
                document.removeEventListener('catalog:cart-sync', this.handleCartSync);
            },
            template: `
                <div>
                    <div :class="{ hidden: hasQuantity }" data-cart-add-state>
                        <button
                            type="button"
                            class="catalog-product-card__cta"
                            @click.prevent="addToCart"
                        >
                            В корзину
                        </button>
                    </div>

                    <div
                        class="catalog-product-card__stepper"
                        :class="{ hidden: !hasQuantity }"
                        data-cart-qty-state
                    >
                        <button
                            type="button"
                            class="catalog-product-card__stepper-btn"
                            aria-label="Уменьшить количество"
                            @click.prevent="decrement"
                        >
                            -
                        </button>
                        <input
                            type="number"
                            :min="minQuantity"
                            :max="maxQuantity"
                            :step="stepQuantity"
                            inputmode="numeric"
                            class="catalog-product-card__stepper-value"
                            data-cart-quantity
                            data-cart-quantity-input
                            v-model="draftQuantity"
                            @change="onQuantityChange"
                            @keydown.enter.prevent="onQuantityChange"
                        >
                        <button
                            type="button"
                            class="catalog-product-card__stepper-btn"
                            aria-label="Увеличить количество"
                            @click.prevent="increment"
                        >
                            +
                        </button>
                    </div>

                    <div v-if="unitsInPackageSummary || colorName" class="catalog-product-card__traits">
                        <p v-if="unitsInPackageSummary" class="catalog-product-card__trait">
                            <span>Упаковка</span>
                            <strong>{{ unitsInPackageSummary }}</strong>
                        </p>
                        <p v-if="colorName" class="catalog-product-card__trait">
                            <span>Цвет</span>
                            <strong>{{ colorName }}</strong>
                        </p>
                    </div>
                </div>
            `,
        });

        app.mount(root);
        root.dataset.vueMountedCart = '1';
    });
};

const mountVueCategoryShowcases = (scope = document) => {
    if (typeof window === 'undefined' || !window.Vue?.createApp) {
        return;
    }

    const { createApp } = window.Vue;
    const roots = scope.querySelectorAll('[data-vue-category-showcase]');

    roots.forEach((root) => {
        if (!(root instanceof HTMLElement) || root.dataset.vueMountedCategoryShowcase === '1') {
            return;
        }

        const rawProps = parseJsonDataset(root.dataset.vueCategoryShowcaseProps, {}) ?? {};
        const items = Array.isArray(rawProps.items)
            ? rawProps.items
                .map((item) => {
                    const slug = typeof item?.slug === 'string' ? item.slug.trim() : '';
                    const url = typeof item?.url === 'string' ? item.url.trim() : '';
                    const name = typeof item?.name === 'string' ? item.name.trim() : '';

                    if (slug === '' || url === '' || name === '') {
                        return null;
                    }

                    const sections = Array.isArray(item?.sections)
                        ? item.sections
                            .map((section) => ({
                                name: typeof section?.name === 'string' ? section.name : '',
                                url: typeof section?.url === 'string' ? section.url : '',
                            }))
                            .filter((section) => section.name !== '')
                        : [];

                    return {
                        id: readIntWithFallback(item?.id, 0),
                        slug,
                        name,
                        url,
                        accentColor: typeof item?.accentColor === 'string' && item.accentColor.trim() !== ''
                            ? item.accentColor
                            : '#163459',
                        childrenCount: readIntWithFallback(item?.childrenCount, 0),
                        productsCount: readIntWithFallback(item?.productsCount, 0),
                        previewImage: typeof item?.previewImage === 'string' ? item.previewImage : '',
                        previewTitle: typeof item?.previewTitle === 'string' ? item.previewTitle : name,
                        mark: typeof item?.mark === 'string' && item.mark.trim() !== ''
                            ? item.mark.trim().slice(0, 2)
                            : name.slice(0, 2).toUpperCase(),
                        sections,
                    };
                })
                .filter((item) => item !== null)
            : [];

        if (items.length === 0) {
            return;
        }

        const initialActiveSlug = items[0].slug;
        const title = typeof rawProps.title === 'string' && rawProps.title.trim() !== ''
            ? rawProps.title
            : 'Основные категории';
        const subtitle = typeof rawProps.subtitle === 'string' ? rawProps.subtitle : '';
        const summary = typeof rawProps.summary === 'string' ? rawProps.summary : '';

        const app = createApp({
            data() {
                return {
                    title,
                    subtitle,
                    summary,
                    items,
                    activeSlug: initialActiveSlug,
                };
            },
            methods: {
                setActive(slug, options = {}) {
                    if (typeof slug !== 'string' || slug.trim() === '') {
                        return;
                    }

                    this.activeSlug = slug;

                    if (options.scrollCard === true) {
                        this.$nextTick(() => {
                            this.scrollCardIntoView(slug);
                        });
                    }
                },
                scrollCardIntoView(slug) {
                    const links = Array.isArray(this.$refs.cardLinks)
                        ? this.$refs.cardLinks
                        : [this.$refs.cardLinks];

                    const activeCard = links.find((node) => (
                        node instanceof HTMLAnchorElement
                        && node.dataset.categorySlug === slug
                    ));

                    if (!(activeCard instanceof HTMLAnchorElement)) {
                        return;
                    }

                    activeCard.scrollIntoView({
                        behavior: 'smooth',
                        inline: 'center',
                        block: 'nearest',
                    });
                },
            },
            template: `
                <div>
                    <div class="catalog-section-head catalog-section-head--clean">
                        <h2 class="catalog-section-title">{{ title }}</h2>
                        <p v-if="subtitle" class="catalog-category-v3__subtitle">{{ subtitle }}</p>
                        <p v-if="summary" class="catalog-category-v3__summary">{{ summary }}</p>
                    </div>

                    <div class="catalog-category-v3__tabs" role="tablist" aria-label="Категории каталога">
                        <button
                            v-for="item in items"
                            :key="item.slug"
                            type="button"
                            class="catalog-category-v3__tab"
                            :class="{ 'is-active': item.slug === activeSlug }"
                            :aria-pressed="item.slug === activeSlug ? 'true' : 'false'"
                            @click="setActive(item.slug, { scrollCard: true })"
                        >
                            <span class="catalog-category-v3__tab-mark">{{ item.mark }}</span>
                            <span class="catalog-category-v3__tab-title">{{ item.name }}</span>
                            <span class="catalog-category-v3__tab-count">{{ item.productsCount }}</span>
                        </button>
                    </div>

                    <div class="catalog-category-showcase mt-4" data-category-rail>
                        <button type="button" class="catalog-category-showcase__arrow is-left" data-category-rail-prev aria-label="Прокрутить категории влево">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M14.5 5 8 12l6.5 7" />
                            </svg>
                        </button>

                        <div class="catalog-category-showcase__viewport" data-category-rail-track>
                            <div class="catalog-category-showcase__track">
                                <a
                                    v-for="(item, index) in items"
                                    :key="item.slug"
                                    ref="cardLinks"
                                    :href="item.url"
                                    class="catalog-category-card reveal-card"
                                    :class="{ 'is-active': item.slug === activeSlug }"
                                    :style="{
                                        animationDelay: (index * 70) + 'ms',
                                        '--category-accent': item.accentColor
                                    }"
                                    :data-category-slug="item.slug"
                                    @mouseenter="setActive(item.slug)"
                                    @focusin="setActive(item.slug)"
                                >
                                    <div class="catalog-category-card__copy">
                                        <div class="catalog-category-card__head">
                                            <span class="catalog-category-card__badge">{{ item.childrenCount }} секц.</span>
                                        </div>

                                        <h3 class="catalog-category-card__title">{{ item.name }}</h3>
                                        <p class="catalog-category-card__meta">{{ item.productsCount }} товаров в наличии</p>

                                        <div v-if="item.sections.length > 0" class="catalog-category-card__sections">
                                            <span
                                                v-for="(section, sectionIndex) in item.sections.slice(0, 4)"
                                                :key="item.slug + '-section-' + sectionIndex"
                                                class="catalog-category-card__section"
                                            >
                                                {{ section.name }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="catalog-category-card__visual">
                                        <div class="catalog-category-card__media">
                                            <img
                                                v-if="item.previewImage"
                                                :src="item.previewImage"
                                                :alt="item.previewTitle || item.name"
                                                class="catalog-category-card__image"
                                                loading="lazy"
                                                decoding="async"
                                            >
                                            <div v-else class="catalog-category-card__mark">{{ item.mark }}</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <button type="button" class="catalog-category-showcase__arrow is-right" data-category-rail-next aria-label="Прокрутить категории вправо">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m9.5 5 6.5 7-6.5 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            `,
        });

        app.mount(root);
        root.dataset.vueMountedCategoryShowcase = '1';
    });
};

const mountVueCatalogIslands = (scope = document) => {
    mountVueCategoryShowcases(scope);
    mountVueFavoriteToggles(scope);
    mountVueCatalogGalleries(scope);
    mountVueCatalogCartControls(scope);
};

const mountVueCatalogFeedSwitchers = (scope = document) => {
    if (typeof window === 'undefined' || !window.Vue?.createApp) {
        return;
    }

    const { createApp } = window.Vue;
    const roots = scope.querySelectorAll('[data-vue-feed-switcher]');

    roots.forEach((root) => {
        if (!(root instanceof HTMLElement) || root.dataset.vueMountedFeedSwitcher === '1') {
            return;
        }

        const rawProps = parseJsonDataset(root.dataset.vueFeedProps, {}) ?? {};
        const items = Array.isArray(rawProps.items)
            ? rawProps.items
                .map((item) => ({
                    key: typeof item?.key === 'string' ? item.key : '',
                    label: typeof item?.label === 'string' ? item.label : '',
                    url: typeof item?.url === 'string' ? item.url : '',
                }))
                .filter((item) => item.key !== '' && item.url !== '')
            : [];

        if (items.length === 0) {
            return;
        }

        const initialFeed = typeof rawProps.initialFeed === 'string' && rawProps.initialFeed !== ''
            ? rawProps.initialFeed
            : items[0].key;

        const app = createApp({
            data() {
                return {
                    items,
                    activeKey: initialFeed,
                    loading: false,
                    pendingActivation: null,
                };
            },
            methods: {
                normalizeUrl(url) {
                    if (typeof url !== 'string' || url.trim() === '') {
                        return '';
                    }

                    try {
                        const parsed = new URL(url, window.location.origin);
                        const normalizedPath = parsed.pathname.length > 1
                            ? parsed.pathname.replace(/\/+$/, '')
                            : parsed.pathname;
                        return `${normalizedPath}${parsed.search}`;
                    } catch (error) {
                        return url;
                    }
                },
                findItemByUrl(url) {
                    const normalizedUrl = this.normalizeUrl(url);

                    if (!normalizedUrl) {
                        return null;
                    }

                    return this.items.find((item) => this.normalizeUrl(item.url) === normalizedUrl) ?? null;
                },
                resolveFeedRoot() {
                    const section = this.$el instanceof HTMLElement
                        ? this.$el.closest('section')
                        : null;
                    if (section instanceof HTMLElement) {
                        const feedRoot = section.querySelector('[data-infinite-feed]');
                        if (feedRoot instanceof HTMLElement) {
                            return feedRoot;
                        }
                    }

                    const fallback = document.querySelector('[data-infinite-feed]');
                    return fallback instanceof HTMLElement ? fallback : null;
                },
                setActiveKeyFromUrl(url) {
                    const matchedItem = this.findItemByUrl(url);
                    if (matchedItem !== null) {
                        this.activeKey = matchedItem.key;
                    }

                    return matchedItem;
                },
                async activate(item, { pushState = true, force = false } = {}) {
                    if (!item) {
                        return;
                    }

                    if (this.loading) {
                        this.pendingActivation = {
                            key: item.key,
                            pushState,
                            force,
                        };
                        return;
                    }

                    if (!force && item.key === this.activeKey) {
                        return;
                    }

                    const feedRoot = this.resolveFeedRoot();
                    const grid = feedRoot?.querySelector('[data-infinite-grid]');

                    if (!(grid instanceof HTMLElement) || !(feedRoot instanceof HTMLElement)) {
                        window.location.assign(item.url);
                        return;
                    }

                    this.loading = true;
                    root.classList.add('is-loading');
                    feedRoot.classList.add('is-feed-switching');
                    grid.setAttribute('aria-busy', 'true');

                    try {
                        const response = await fetch(item.url, {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        });

                        if (!response.ok) {
                            throw new Error('Feed switch failed');
                        }

                        const payload = await response.json();
                        const html = typeof payload?.html === 'string' ? payload.html : '';
                        grid.innerHTML = html;
                        mountVueCatalogIslands(grid);

                        const nextPageUrl = typeof payload?.nextPageUrl === 'string'
                            ? payload.nextPageUrl
                            : '';

                        if (typeof feedRoot.__setInfiniteNextPage === 'function') {
                            feedRoot.__setInfiniteNextPage(nextPageUrl);
                        } else {
                            feedRoot.dataset.nextPage = nextPageUrl;
                            const trigger = feedRoot.querySelector('[data-infinite-trigger]');
                            if (trigger instanceof HTMLElement) {
                                trigger.classList.toggle('hidden', nextPageUrl.trim() === '');
                            }
                        }

                        this.activeKey = item.key;

                        if (pushState && typeof window.history?.pushState === 'function') {
                            window.history.pushState({ feed: item.key }, '', item.url);
                        }
                    } catch (error) {
                        window.location.assign(item.url);
                    } finally {
                        this.loading = false;
                        root.classList.remove('is-loading');
                        feedRoot.classList.remove('is-feed-switching');
                        grid.setAttribute('aria-busy', 'false');

                        const pendingActivation = this.pendingActivation;
                        this.pendingActivation = null;

                        if (
                            pendingActivation
                            && pendingActivation.key !== this.activeKey
                        ) {
                            const nextItem = this.items.find((candidate) => candidate.key === pendingActivation.key);
                            if (nextItem) {
                                this.activate(nextItem, {
                                    pushState: pendingActivation.pushState,
                                    force: true,
                                });
                            }
                        }
                    }
                },
                onPopState() {
                    const matchedItem = this.findItemByUrl(window.location.href);
                    if (matchedItem === null) {
                        return;
                    }

                    this.activate(matchedItem, { pushState: false, force: true });
                },
            },
            mounted() {
                this.setActiveKeyFromUrl(window.location.href);
                window.addEventListener('popstate', this.onPopState);
            },
            beforeUnmount() {
                window.removeEventListener('popstate', this.onPopState);
            },
            template: `
                <a
                    v-for="item in items"
                    :key="item.key"
                    :href="item.url"
                    class="catalog-feed-filter"
                    :class="{ 'is-active': activeKey === item.key }"
                    :aria-current="activeKey === item.key ? 'page' : null"
                    @click.prevent="activate(item)"
                >
                    {{ item.label }}
                </a>
            `,
        });

        app.mount(root);
        root.dataset.vueMountedFeedSwitcher = '1';
    });
};

const mountVueCartLines = (scope = document) => {
    if (typeof window === 'undefined' || !window.Vue?.createApp) {
        return;
    }

    const { createApp } = window.Vue;
    const roots = scope.querySelectorAll('[data-vue-cart-line]');

    roots.forEach((root) => {
        if (!(root instanceof HTMLElement) || root.dataset.vueMountedCartLine === '1') {
            return;
        }

        const rawProps = parseJsonDataset(root.dataset.vueCartLineProps, {}) ?? {};
        const pricingRaw = rawProps.pricing && typeof rawProps.pricing === 'object'
            ? rawProps.pricing
            : {};
        const quantityConstraints = resolveQuantityConstraints({
            min: rawProps.minQuantity,
            step: rawProps.stepQuantity,
            max: rawProps.maxQuantity,
        });

        const parseAmount = (value) => {
            const parsed = Number.parseFloat(`${value ?? ''}`.replace(',', '.'));
            return Number.isFinite(parsed) ? parsed : null;
        };

        const initialQuantity = normalizeCartQuantity(
            rawProps.quantity,
            quantityConstraints.min,
            quantityConstraints,
        );

        const productUrl = typeof rawProps.productUrl === 'string' ? rawProps.productUrl : '';
        const fallbackImageUrl = typeof rawProps.fallbackImageUrl === 'string' ? rawProps.fallbackImageUrl : '';
        const imageUrl = typeof rawProps.imageUrl === 'string' && rawProps.imageUrl.trim() !== ''
            ? rawProps.imageUrl
            : fallbackImageUrl;

        const app = createApp({
            data() {
                return {
                    itemId: readIntWithFallback(rawProps.itemId, 0),
                    title: typeof rawProps.title === 'string' ? rawProps.title : 'Товар из каталога',
                    categoryName: typeof rawProps.categoryName === 'string' ? rawProps.categoryName : 'Каталог',
                    vendorCode: typeof rawProps.vendorCode === 'string' ? rawProps.vendorCode : '',
                    unitLabel: typeof rawProps.unitLabel === 'string' ? rawProps.unitLabel : '',
                    colorName: typeof rawProps.colorName === 'string' ? rawProps.colorName : '',
                    unitsInPackageSummary: typeof rawProps.unitsInPackageSummary === 'string'
                        ? rawProps.unitsInPackageSummary
                        : '',
                    imageUrl,
                    fallbackImageUrl,
                    productUrl,
                    quantity: initialQuantity,
                    draftQuantity: `${initialQuantity}`,
                    minQuantity: quantityConstraints.min,
                    stepQuantity: quantityConstraints.step,
                    maxQuantity: quantityConstraints.max,
                    updateUrl: typeof rawProps.updateUrl === 'string' ? rawProps.updateUrl : '',
                    destroyUrl: typeof rawProps.destroyUrl === 'string' ? rawProps.destroyUrl : '',
                    csrfToken: typeof rawProps.csrfToken === 'string' ? rawProps.csrfToken : '',
                    paymentMethod: typeof rawProps.paymentMethod === 'string' ? rawProps.paymentMethod : 'bank_transfer',
                    baseUnitAmount: parseAmount(pricingRaw.baseUnitAmount),
                    discountUnitAmount: parseAmount(pricingRaw.discountUnitAmount),
                    baseLineAmount: parseAmount(pricingRaw.baseLineAmount),
                    discountLineAmount: parseAmount(pricingRaw.discountLineAmount),
                    isLoading: false,
                };
            },
            computed: {
                isCash() {
                    return this.paymentMethod === 'cash';
                },
                hasDiscount() {
                    return this.baseUnitAmount !== null
                        && this.discountUnitAmount !== null
                        && this.baseUnitAmount > this.discountUnitAmount;
                },
                unitAmount() {
                    return this.isCash ? this.discountUnitAmount : this.baseUnitAmount;
                },
                lineAmount() {
                    return this.isCash ? this.discountLineAmount : this.baseLineAmount;
                },
                hasPrice() {
                    return this.unitAmount !== null;
                },
                priceLabel() {
                    return this.isCash ? 'Со скидкой' : 'Без скидки';
                },
                paymentNote() {
                    return this.isCash ? 'Наличный расчет' : 'Безналичный расчет';
                },
                metaLine() {
                    const parts = [];

                    if (this.vendorCode) {
                        parts.push(`Артикул ${this.vendorCode}`);
                    } else {
                        parts.push('Каталог');
                    }

                    if (this.unitLabel) {
                        parts.push(this.unitLabel);
                    }

                    if (this.colorName) {
                        parts.push(`Цвет: ${this.colorName}`);
                    }

                    if (this.unitsInPackageSummary) {
                        parts.push(`Упаковка: ${this.unitsInPackageSummary}`);
                    }

                    return parts.join(' · ');
                },
            },
            methods: {
                formatAmount(amount) {
                    return formatRubles(amount);
                },
                normalize(value, fallback) {
                    return normalizeCartQuantity(value, fallback, {
                        min: this.minQuantity,
                        step: this.stepQuantity,
                        max: this.maxQuantity,
                    });
                },
                applyPricing(payloadItem) {
                    if (!payloadItem || typeof payloadItem !== 'object') {
                        return;
                    }

                    if (payloadItem.base_unit_amount !== undefined) {
                        this.baseUnitAmount = parseAmount(payloadItem.base_unit_amount);
                    }
                    if (payloadItem.discount_unit_amount !== undefined) {
                        this.discountUnitAmount = parseAmount(payloadItem.discount_unit_amount);
                    }
                    if (payloadItem.base_line_amount !== undefined) {
                        this.baseLineAmount = parseAmount(payloadItem.base_line_amount);
                    }
                    if (payloadItem.discount_line_amount !== undefined) {
                        this.discountLineAmount = parseAmount(payloadItem.discount_line_amount);
                    }
                },
                syncCartCount(cartCount) {
                    const parsed = Number.parseInt(`${cartCount ?? ''}`, 10);
                    if (!Number.isFinite(parsed)) {
                        return;
                    }

                    document.querySelectorAll('[data-cart-count]').forEach((node) => {
                        if (node instanceof HTMLElement) {
                            node.textContent = `${parsed}`;
                        }
                    });
                },
                handlePaymentSync(event) {
                    const detail = event instanceof CustomEvent ? event.detail : null;
                    const paymentMethod = typeof detail?.paymentMethod === 'string'
                        ? detail.paymentMethod
                        : '';

                    if (paymentMethod === 'cash' || paymentMethod === 'bank_transfer') {
                        this.paymentMethod = paymentMethod;
                    }
                },
                onQuantityChange() {
                    const normalized = this.normalize(
                        this.draftQuantity,
                        this.quantity > 0 ? this.quantity : this.minQuantity,
                    );
                    this.draftQuantity = `${normalized}`;
                    this.submitQuantity(normalized);
                },
                increment() {
                    this.submitQuantity(this.quantity + this.stepQuantity);
                },
                decrement() {
                    this.submitQuantity(this.quantity - this.stepQuantity);
                },
                async submitQuantity(nextQuantity) {
                    if (this.isLoading) {
                        return;
                    }

                    const previousQuantity = this.quantity;
                    const resolvedQuantity = this.normalize(nextQuantity, previousQuantity);

                    if (resolvedQuantity === previousQuantity) {
                        this.draftQuantity = `${resolvedQuantity}`;
                        return;
                    }

                    if (!this.updateUrl) {
                        return;
                    }

                    this.isLoading = true;

                    const body = new FormData();
                    if (this.csrfToken) {
                        body.append('_token', this.csrfToken);
                    }
                    body.append('_method', 'PATCH');
                    body.append('quantity', `${resolvedQuantity}`);
                    body.append('payment_method', this.paymentMethod);

                    try {
                        const response = await fetch(this.updateUrl, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body,
                            credentials: 'same-origin',
                        });

                        if (response.status === 401 || response.status === 419) {
                            window.location.reload();
                            return;
                        }

                        if (!response.ok) {
                            throw new Error('Cart line update failed');
                        }

                        const payload = await response.json();
                        const payloadQuantity = Number.parseInt(`${payload?.quantity ?? ''}`, 10);
                        this.quantity = Number.isFinite(payloadQuantity)
                            ? this.normalize(payloadQuantity, this.minQuantity)
                            : resolvedQuantity;
                        this.draftQuantity = `${this.quantity}`;

                        this.applyPricing(payload?.item);
                        this.syncCartCount(payload?.cartCount);
                        syncCartSummaryNodes(payload?.summary);
                    } catch (error) {
                        this.quantity = previousQuantity;
                        this.draftQuantity = `${previousQuantity}`;
                        pushToast('Ошибка', 'Не удалось обновить корзину. Проверьте соединение.');
                    } finally {
                        this.isLoading = false;
                    }
                },
                async removeItem() {
                    if (this.isLoading || !this.destroyUrl) {
                        return;
                    }

                    this.isLoading = true;

                    const body = new FormData();
                    if (this.csrfToken) {
                        body.append('_token', this.csrfToken);
                    }
                    body.append('_method', 'DELETE');
                    body.append('payment_method', this.paymentMethod);

                    try {
                        const response = await fetch(this.destroyUrl, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body,
                            credentials: 'same-origin',
                        });

                        if (response.status === 401 || response.status === 419) {
                            window.location.reload();
                            return;
                        }

                        if (!response.ok) {
                            throw new Error('Cart line remove failed');
                        }

                        const payload = await response.json();
                        this.syncCartCount(payload?.cartCount);
                        syncCartSummaryNodes(payload?.summary);

                        const row = this.$el instanceof HTMLElement
                            ? this.$el.closest('[data-cart-item]')
                            : null;

                        if (row instanceof HTMLElement) {
                            row.remove();
                        }

                        const hasRows = document.querySelector('[data-cart-item]') instanceof HTMLElement;
                        if (!hasRows) {
                            window.location.reload();
                        }
                    } catch (error) {
                        pushToast('Ошибка', 'Не удалось удалить товар из корзины.');
                    } finally {
                        this.isLoading = false;
                    }
                },
                onImageError(event) {
                    const target = event.target;
                    if (!(target instanceof HTMLImageElement)) {
                        return;
                    }

                    if (this.fallbackImageUrl && target.src !== this.fallbackImageUrl) {
                        target.src = this.fallbackImageUrl;
                    }
                },
            },
            mounted() {
                document.addEventListener('catalog:cart-payment-sync', this.handlePaymentSync);
            },
            beforeUnmount() {
                document.removeEventListener('catalog:cart-payment-sync', this.handlePaymentSync);
            },
            template: `
                <div class="catalog-cart-item">
                    <div class="catalog-cart-item__visual">
                        <img
                            :src="imageUrl"
                            :alt="title"
                            class="catalog-cart-item__image"
                            @error="onImageError"
                        >
                    </div>

                    <div class="catalog-cart-item__content">
                        <div>
                            <p class="catalog-cart-item__eyebrow">{{ categoryName }}</p>
                            <a v-if="productUrl" :href="productUrl" class="catalog-cart-item__title">{{ title }}</a>
                            <p v-else class="catalog-cart-item__title">{{ title }}</p>
                            <p class="catalog-cart-item__meta">{{ metaLine }}</p>
                        </div>

                        <div class="catalog-cart-item__controls">
                            <div class="catalog-cart-qty-form catalog-qty-control">
                                <button
                                    type="button"
                                    class="catalog-qty-control__button"
                                    :disabled="isLoading"
                                    @click.prevent="decrement"
                                >-</button>
                                <input
                                    type="number"
                                    :min="minQuantity"
                                    :max="maxQuantity"
                                    :step="stepQuantity"
                                    :disabled="isLoading"
                                    class="catalog-clean-input"
                                    v-model="draftQuantity"
                                    @change="onQuantityChange"
                                    @keydown.enter.prevent="onQuantityChange"
                                >
                                <button
                                    type="button"
                                    class="catalog-qty-control__button"
                                    :disabled="isLoading"
                                    @click.prevent="increment"
                                >+</button>
                            </div>

                            <button
                                type="button"
                                class="catalog-inline-action"
                                :disabled="isLoading"
                                @click.prevent="removeItem"
                            >Удалить</button>
                        </div>
                    </div>

                    <div class="catalog-cart-item__price">
                        <p class="catalog-cart-item__price-label">{{ priceLabel }}</p>
                        <p v-if="hasDiscount && isCash" class="catalog-cart-item__compare-price">
                            {{ formatAmount(baseUnitAmount) }}
                        </p>
                        <p
                            v-if="hasPrice"
                            class="catalog-cart-item__price-value"
                            :class="{ 'catalog-cart-item__price-value--accent': hasDiscount && isCash }"
                        >
                            {{ formatAmount(unitAmount) }}
                        </p>
                        <p v-else class="catalog-cart-item__price-value catalog-cart-item__price-value--empty">
                            Цена по запросу
                        </p>
                        <p v-if="hasPrice" class="catalog-cart-item__line-total">
                            Итого: {{ formatAmount(lineAmount) }}
                        </p>
                        <p class="catalog-cart-item__payment-note">{{ paymentNote }}</p>
                    </div>
                </div>
            `,
        });

        app.mount(root);
        root.dataset.vueMountedCartLine = '1';
    });
};

const mountVueCartCheckout = (scope = document) => {
    if (typeof window === 'undefined' || !window.Vue?.createApp) {
        return;
    }

    const { createApp } = window.Vue;
    const roots = scope.querySelectorAll('[data-vue-cart-checkout]');

    roots.forEach((root) => {
        if (!(root instanceof HTMLElement) || root.dataset.vueMountedCartCheckout === '1') {
            return;
        }

        const rawProps = parseJsonDataset(root.dataset.vueCartCheckoutProps, {}) ?? {};
        const summaryRaw = rawProps.summary && typeof rawProps.summary === 'object'
            ? rawProps.summary
            : {};
        const paymentOptions = Array.isArray(rawProps.paymentOptions)
            ? rawProps.paymentOptions
                .map((option) => ({
                    value: typeof option?.value === 'string' ? option.value : '',
                    label: typeof option?.label === 'string' ? option.label : '',
                    note: typeof option?.note === 'string' ? option.note : '',
                }))
                .filter((option) => option.value !== '' && option.label !== '')
            : [];

        if (paymentOptions.length === 0) {
            return;
        }

        const parseIntSafe = (value, fallback = 0) => {
            const parsed = Number.parseInt(`${value ?? ''}`, 10);
            return Number.isFinite(parsed) ? parsed : fallback;
        };

        const parseFloatSafe = (value, fallback = null) => {
            const parsed = Number.parseFloat(`${value ?? ''}`.replace(',', '.'));
            return Number.isFinite(parsed) ? parsed : fallback;
        };

        const initialPaymentMethod = typeof rawProps.selectedPaymentMethod === 'string'
            && paymentOptions.some((option) => option.value === rawProps.selectedPaymentMethod)
            ? rawProps.selectedPaymentMethod
            : paymentOptions[0].value;

        const app = createApp({
            data() {
                return {
                    paymentMethod: initialPaymentMethod,
                    paymentOptions,
                    summary: {
                        itemsCount: parseIntSafe(summaryRaw.itemsCount, 0),
                        totalQuantity: parseIntSafe(summaryRaw.totalQuantity, 0),
                        pricedItemsCount: parseIntSafe(summaryRaw.pricedItemsCount, 0),
                        unpricedItemsCount: parseIntSafe(summaryRaw.unpricedItemsCount, 0),
                        baseTotal: parseFloatSafe(summaryRaw.baseTotal, null),
                        discountTotal: parseFloatSafe(summaryRaw.discountTotal, null),
                    },
                };
            },
            computed: {
                isCash() {
                    return this.paymentMethod === 'cash';
                },
                totalAmount() {
                    return this.isCash ? this.summary.discountTotal : this.summary.baseTotal;
                },
                hasUnpricedItems() {
                    return this.summary.unpricedItemsCount > 0;
                },
            },
            methods: {
                formatAmount(amount) {
                    return formatRubles(amount);
                },
                formatCount(value) {
                    const parsed = Number.parseInt(`${value ?? ''}`, 10);
                    return Number.isFinite(parsed) ? `${parsed}` : '0';
                },
                applySummary(summary) {
                    if (!summary || typeof summary !== 'object') {
                        return;
                    }

                    const nextItemsCount = parseIntSafe(
                        summary.items_count ?? summary.itemsCount,
                        this.summary.itemsCount,
                    );
                    const nextTotalQuantity = parseIntSafe(
                        summary.total_quantity ?? summary.totalQuantity,
                        this.summary.totalQuantity,
                    );
                    const nextPricedItemsCount = parseIntSafe(
                        summary.priced_items_count ?? summary.pricedItemsCount,
                        this.summary.pricedItemsCount,
                    );
                    const nextUnpricedItemsCount = parseIntSafe(
                        summary.unpriced_items_count ?? summary.unpricedItemsCount,
                        this.summary.unpricedItemsCount,
                    );
                    const nextBaseTotal = parseFloatSafe(
                        summary.base_total_amount ?? summary.baseTotal,
                        this.summary.baseTotal,
                    );
                    const nextDiscountTotal = parseFloatSafe(
                        summary.discount_total_amount ?? summary.discountTotal,
                        this.summary.discountTotal,
                    );

                    this.summary.itemsCount = nextItemsCount;
                    this.summary.totalQuantity = nextTotalQuantity;
                    this.summary.pricedItemsCount = nextPricedItemsCount;
                    this.summary.unpricedItemsCount = nextUnpricedItemsCount;
                    this.summary.baseTotal = nextBaseTotal;
                    this.summary.discountTotal = nextDiscountTotal;

                    const nextPaymentMethod = summary.payment_method ?? summary.paymentMethod;
                    if (
                        typeof nextPaymentMethod === 'string'
                        && this.paymentOptions.some((option) => option.value === nextPaymentMethod)
                    ) {
                        this.paymentMethod = nextPaymentMethod;
                    }
                },
                summaryPayload() {
                    return {
                        items_count: this.summary.itemsCount,
                        total_quantity: this.summary.totalQuantity,
                        priced_items_count: this.summary.pricedItemsCount,
                        unpriced_items_count: this.summary.unpricedItemsCount,
                        base_total_amount: this.summary.baseTotal,
                        discount_total_amount: this.summary.discountTotal,
                        payment_method: this.paymentMethod,
                    };
                },
                publishSummary() {
                    syncCartSummaryNodes(this.summaryPayload());
                },
                emitPaymentSync() {
                    this.publishSummary();
                    document.dispatchEvent(new CustomEvent('catalog:cart-payment-sync', {
                        detail: {
                            paymentMethod: this.paymentMethod,
                        },
                    }));
                },
                handleSummarySync(event) {
                    const detail = event instanceof CustomEvent ? event.detail : null;
                    this.applySummary(detail?.summary ?? null);
                },
                handlePaymentSync(event) {
                    const detail = event instanceof CustomEvent ? event.detail : null;
                    const paymentMethod = typeof detail?.paymentMethod === 'string'
                        ? detail.paymentMethod
                        : '';

                    if (this.paymentOptions.some((option) => option.value === paymentMethod)) {
                        if (this.paymentMethod === paymentMethod) {
                            return;
                        }

                        this.paymentMethod = paymentMethod;
                        this.publishSummary();
                    }
                },
                onPaymentChange() {
                    this.emitPaymentSync();
                },
            },
            mounted() {
                document.addEventListener('catalog:cart-summary-sync', this.handleSummarySync);
                document.addEventListener('catalog:cart-payment-sync', this.handlePaymentSync);
                this.emitPaymentSync();
            },
            beforeUnmount() {
                document.removeEventListener('catalog:cart-summary-sync', this.handleSummarySync);
                document.removeEventListener('catalog:cart-payment-sync', this.handlePaymentSync);
            },
            template: `
                <div class="catalog-checkout-v2__panel">
                    <div class="catalog-checkout-v2__head">
                        <h2 class="catalog-checkout-v2__title">Подтверждение заявки</h2>
                    </div>

                    <div class="catalog-checkout-v2__summary">
                        <div class="catalog-summary-row">
                            <span>Позиций</span>
                            <strong data-cart-items-count>{{ formatCount(summary.itemsCount) }}</strong>
                        </div>
                        <div class="catalog-summary-row">
                            <span>Количество</span>
                            <strong data-cart-total-quantity>{{ formatCount(summary.totalQuantity) }}</strong>
                        </div>
                        <div class="catalog-summary-row">
                            <span>С ценой</span>
                            <strong data-cart-priced-count>{{ formatCount(summary.pricedItemsCount) }}</strong>
                        </div>
                        <div v-if="hasUnpricedItems" class="catalog-summary-row">
                            <span>По запросу</span>
                            <strong data-cart-unpriced-count>{{ formatCount(summary.unpricedItemsCount) }}</strong>
                        </div>
                        <div class="catalog-summary-row is-total">
                            <span>Итого</span>
                            <strong
                                data-cart-summary-total
                                :data-base-total="summary.baseTotal ?? ''"
                                :data-discount-total="summary.discountTotal ?? ''"
                            >
                                {{ formatAmount(totalAmount) }}
                            </strong>
                        </div>
                    </div>

                    <div class="catalog-checkout-v2__payments">
                        <label class="catalog-filter-title">Тип оплаты</label>

                        <div class="catalog-payment-options">
                            <label
                                v-for="option in paymentOptions"
                                :key="option.value"
                                class="catalog-payment-option"
                            >
                                <input
                                    type="radio"
                                    name="payment_method"
                                    :value="option.value"
                                    data-cart-payment-method
                                    v-model="paymentMethod"
                                    @change="onPaymentChange"
                                >
                                <span class="catalog-payment-option__content">
                                    <strong>{{ option.label }}</strong>
                                    <small>{{ option.note }}</small>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            `,
        });

        app.mount(root);
        root.dataset.vueMountedCartCheckout = '1';
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

const setupHeaderScrollState = () => {
    const header = document.querySelector('.catalog-site-header');

    if (!(header instanceof HTMLElement)) {
        return;
    }

    let ticking = false;
    const sync = () => {
        header.classList.toggle('is-scrolled', window.scrollY > 18);
        ticking = false;
    };

    window.addEventListener('scroll', () => {
        if (ticking) {
            return;
        }

        ticking = true;
        window.requestAnimationFrame(sync);
    }, { passive: true });

    sync();
};

const setupResponsiveUiMetrics = () => {
    const root = document.documentElement;

    if (!(root instanceof HTMLElement)) {
        return;
    }

    const apply = () => {
        const viewportHeight = window.visualViewport?.height ?? window.innerHeight;
        root.style.setProperty('--catalog-vh', `${Math.max(1, viewportHeight) * 0.01}px`);

        const isMobileLayout = window.matchMedia('(max-width: 900px)').matches;
        const mobileNav = document.querySelector('.catalog-mobile-nav');
        const mobileNavVisible = isMobileLayout
            && mobileNav instanceof HTMLElement
            && window.getComputedStyle(mobileNav).display !== 'none';
        const navHeight = mobileNavVisible
            ? Math.ceil(mobileNav.getBoundingClientRect().height)
            : 0;

        root.style.setProperty('--catalog-mobile-nav-height', `${Math.max(0, navHeight)}px`);
    };

    const scheduleApply = () => window.requestAnimationFrame(apply);

    window.addEventListener('resize', scheduleApply, { passive: true });
    window.addEventListener('orientationchange', scheduleApply);

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', scheduleApply);
    }

    apply();
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
        const loadingLabel = trigger.dataset.loadingLabel || 'Подгружаем еще товары';
        const fallbackLabel = trigger.dataset.fallbackLabel || 'Показать еще';
        const retryLabel = 'Повторить загрузку';

        const setLoadingState = (loading) => {
            feedRoot.dataset.loading = loading ? '1' : '0';

            if (loader instanceof HTMLElement) {
                loader.classList.toggle('hidden', !loading);
            }

            if (button instanceof HTMLButtonElement) {
                button.disabled = loading;
            }
        };

        const showFallback = (label = fallbackLabel) => {
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

        const applyFeedState = (nextPageUrl = '') => {
            const resolvedNextPage = typeof nextPageUrl === 'string' ? nextPageUrl : '';
            const hasNextPage = resolvedNextPage.trim() !== '';

            feedRoot.dataset.nextPage = hasNextPage ? resolvedNextPage : '';
            setLoadingState(false);
            hideFallback();
            trigger.classList.toggle('hidden', !hasNextPage);

            if (observer) {
                observer.unobserve(trigger);

                if (hasNextPage) {
                    requestAnimationFrame(() => observer?.observe(trigger));
                }
            } else if (hasNextPage) {
                showFallback(fallbackLabel);
            }
        };

        const stopFeed = () => {
            applyFeedState('');
        };

        feedRoot.__setInfiniteNextPage = (nextPageUrl = '') => {
            applyFeedState(nextPageUrl);
        };

        if (loader instanceof HTMLElement) {
            const loaderLabelNode = loader.querySelector('.catalog-infinite-loader__label');
            if (loaderLabelNode instanceof HTMLElement) {
                loaderLabelNode.textContent = loadingLabel;
            }
        }

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
                    mountVueCatalogIslands(grid);
                }

                if (payload.nextPageUrl) {
                    applyFeedState(payload.nextPageUrl);
                    return;
                }

                stopFeed();
            } catch (error) {
                setLoadingState(false);
                trigger.classList.remove('hidden');
                showFallback(retryLabel);
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
        }

        applyFeedState(feedRoot.dataset.nextPage ?? '');
    });
};

const setupSupportWidget = () => {
    const widget = document.querySelector('[data-support-widget]');

    if (!(widget instanceof HTMLElement)) {
        return;
    }

    const dialog = widget.querySelector('[role="dialog"]');
    const body = widget.querySelector('[data-support-widget-body]');
    const form = widget.querySelector('[data-support-widget-form]') ?? widget.querySelector('form');
    const textarea = widget.querySelector('textarea[name="message"]');
    const readUrl = widget.dataset.supportWidgetReadUrl ?? '';
    const csrfToken = form instanceof HTMLFormElement
        ? (form.querySelector('input[name="_token"]')?.value ?? '')
        : '';
    const openButtons = Array.from(document.querySelectorAll('[data-support-widget-open]'));
    const closeButtons = Array.from(widget.querySelectorAll('[data-support-widget-close]'));
    const submitButton = form instanceof HTMLFormElement
        ? form.querySelector('[data-support-widget-submit]') ?? form.querySelector('button[type="submit"]')
        : null;
    const feedback = form instanceof HTMLFormElement
        ? form.querySelector('[data-support-widget-feedback]')
        : null;
    const baseFeedbackText = feedback instanceof HTMLElement
        ? feedback.textContent?.trim() ?? ''
        : '';
    let lastOpenTrigger = null;
    let isSubmitting = false;
    let isMarkingRead = false;

    const setWidgetOpenState = (open) => {
        document.body.classList.toggle('catalog-support-widget-open', open);
        document.documentElement.classList.toggle('catalog-support-widget-open', open);
    };

    const setSubmittingState = (submitting) => {
        if (!(submitButton instanceof HTMLButtonElement)) {
            return;
        }

        submitButton.disabled = submitting;
        submitButton.classList.toggle('is-loading', submitting);
    };

    const setFeedbackText = (text, isError = false) => {
        if (!(feedback instanceof HTMLElement)) {
            return;
        }

        feedback.textContent = `${text ?? ''}`.trim();
        feedback.classList.toggle('catalog-support-widget__error', isError);
        feedback.classList.toggle('catalog-support-widget__hint', !isError);
    };

    const syncBodyScroll = () => {
        if (body instanceof HTMLElement) {
            body.scrollTop = body.scrollHeight;
        }
    };

    const markThreadRead = async () => {
        if (readUrl.trim() === '' || csrfToken.trim() === '' || isMarkingRead) {
            return;
        }

        isMarkingRead = true;

        try {
            await fetch(readUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
            });
        } catch (error) {
            // Ignore receipt transport failures.
        } finally {
            isMarkingRead = false;
        }
    };

    const openWidget = (trigger = null) => {
        if (trigger instanceof HTMLElement) {
            lastOpenTrigger = trigger;
        }

        widget.classList.remove('hidden');
        setWidgetOpenState(true);
        syncBodyScroll();
        void markThreadRead();

        window.setTimeout(() => {
            if (textarea instanceof HTMLTextAreaElement) {
                textarea.focus();
            } else if (dialog instanceof HTMLElement) {
                dialog.focus();
            }
        }, 40);
    };

    const closeWidget = () => {
        if (widget.classList.contains('hidden')) {
            return;
        }

        widget.classList.add('hidden');
        setWidgetOpenState(false);
        isSubmitting = false;
        setSubmittingState(false);

        if (baseFeedbackText !== '') {
            setFeedbackText(baseFeedbackText, false);
        }

        window.setTimeout(() => {
            if (lastOpenTrigger instanceof HTMLElement && document.contains(lastOpenTrigger)) {
                lastOpenTrigger.focus({ preventScroll: true });
            }
        }, 30);
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', () => {
            openWidget(button);
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

    if (textarea instanceof HTMLTextAreaElement && form instanceof HTMLFormElement) {
        textarea.addEventListener('keydown', (event) => {
            const hasSubmitHotkey = event.key === 'Enter' && (event.ctrlKey || event.metaKey);

            if (!hasSubmitHotkey) {
                return;
            }

            event.preventDefault();

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.dispatchEvent(new Event('submit', { cancelable: true }));
            }
        });
    }

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

            if (isSubmitting) {
                return;
            }

            isSubmitting = true;
            setSubmittingState(true);
            setFeedbackText('Отправляем сообщение...', false);

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
                                <span class="catalog-message-meta">
                                    <time>${payload.sentAt || ''}</time>
                                    <span class="catalog-message-checks" title="Отправлено" aria-label="Отправлено">✓</span>
                                </span>
                            </footer>
                        </div>
                    `;

                    const empty = thread.querySelector('.catalog-support-widget__empty');
                    empty?.remove();
                    thread.append(wrapper);
                    syncBodyScroll();
                }

                textarea.value = '';
                setFeedbackText('Сообщение отправлено. Менеджер увидит его в чате.', false);
            } catch (error) {
                pushToast('Ошибка', 'Сообщение не отправлено. Попробуйте еще раз.');
                setFeedbackText('Ошибка отправки. Проверьте сеть и попробуйте еще раз.', true);
            } finally {
                isSubmitting = false;
                setSubmittingState(false);
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

const syncCartSummaryNodes = (summary) => {
    if (!summary || typeof summary !== 'object') {
        return;
    }

    const readCount = (candidate, fallback = null) => {
        const parsed = Number.parseInt(`${candidate ?? ''}`, 10);
        return Number.isFinite(parsed) ? parsed : fallback;
    };

    const readAmount = (candidate, fallback = null) => {
        const parsed = Number.parseFloat(`${candidate ?? ''}`.replace(',', '.'));
        return Number.isFinite(parsed) ? parsed : fallback;
    };

    const itemsCount = readCount(summary.items_count ?? summary.itemsCount, null);
    const totalQuantity = readCount(summary.total_quantity ?? summary.totalQuantity, null);
    const pricedItemsCount = readCount(summary.priced_items_count ?? summary.pricedItemsCount, null);
    const unpricedItemsCount = readCount(summary.unpriced_items_count ?? summary.unpricedItemsCount, null);

    if (itemsCount !== null) {
        document.querySelectorAll('[data-cart-items-count]').forEach((node) => {
            if (node instanceof HTMLElement) {
                node.textContent = `${itemsCount}`;
            }
        });
    }

    if (totalQuantity !== null) {
        document.querySelectorAll('[data-cart-total-quantity]').forEach((node) => {
            if (node instanceof HTMLElement) {
                node.textContent = `${totalQuantity}`;
            }
        });
    }

    if (pricedItemsCount !== null) {
        document.querySelectorAll('[data-cart-priced-count]').forEach((node) => {
            if (node instanceof HTMLElement) {
                node.textContent = `${pricedItemsCount}`;
            }
        });
    }

    if (unpricedItemsCount !== null) {
        document.querySelectorAll('[data-cart-unpriced-count]').forEach((node) => {
            if (node instanceof HTMLElement) {
                node.textContent = `${unpricedItemsCount}`;
            }
        });
    }

    const baseTotal = readAmount(summary.base_total_amount ?? summary.baseTotal, null);
    const discountTotal = readAmount(summary.discount_total_amount ?? summary.discountTotal, null);
    const explicitPaymentMethod = summary.payment_method ?? summary.paymentMethod;
    const selectedPaymentInput = document.querySelector('[data-cart-payment-method]:checked');
    const paymentMethod = typeof explicitPaymentMethod === 'string'
        ? explicitPaymentMethod
        : selectedPaymentInput instanceof HTMLInputElement
            ? selectedPaymentInput.value
            : 'bank_transfer';
    const nextAmount = paymentMethod === 'cash' ? discountTotal : baseTotal;

    document.querySelectorAll('[data-cart-hero-total], [data-cart-summary-total]').forEach((node) => {
        if (!(node instanceof HTMLElement)) {
            return;
        }

        if (baseTotal !== null) {
            node.dataset.baseTotal = `${baseTotal}`;
        }
        if (discountTotal !== null) {
            node.dataset.discountTotal = `${discountTotal}`;
        }

        node.textContent = formatRubles(nextAmount);
    });

    document.dispatchEvent(new CustomEvent('catalog:cart-summary-sync', {
        detail: {
            summary: {
                ...summary,
                items_count: itemsCount ?? summary.items_count ?? summary.itemsCount ?? 0,
                total_quantity: totalQuantity ?? summary.total_quantity ?? summary.totalQuantity ?? 0,
                priced_items_count: pricedItemsCount ?? summary.priced_items_count ?? summary.pricedItemsCount ?? 0,
                unpriced_items_count: unpricedItemsCount ?? summary.unpriced_items_count ?? summary.unpricedItemsCount ?? 0,
                base_total_amount: baseTotal ?? summary.base_total_amount ?? summary.baseTotal ?? null,
                discount_total_amount: discountTotal ?? summary.discount_total_amount ?? summary.discountTotal ?? null,
                payment_method: paymentMethod,
            },
        },
    }));
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

        document.dispatchEvent(new CustomEvent('catalog:cart-payment-sync', {
            detail: {
                paymentMethod,
            },
        }));
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
        syncCartSummaryNodes(summary);
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
    const messageKey = 'major:lastMessageAt';
    const orderKey = 'major:lastOrderAt';

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
                    ? (form.dataset.managerTitle || 'Создать менеджера')
                    : (form.dataset.clientTitle || 'Создать клиента');
            }

            if (description instanceof HTMLElement) {
                description.textContent = role === 'manager'
                    ? (form.dataset.managerDescription || '')
                    : (form.dataset.clientDescription || '');
            }

            if (submit instanceof HTMLElement) {
                submit.textContent = role === 'manager'
                    ? (form.dataset.managerSubmit || 'Создать менеджера')
                    : (form.dataset.clientSubmit || 'Создать клиента');
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
    mountVueCatalogIslands();
    setupProductCardCartControls();
    setupProductGalleries();
    setupCatalogCardImageModes();
    setupHomeBanners();
    setupCatalogMenus();
    setupHeaderScrollState();
    setupResponsiveUiMetrics();
    setupCategoryRails();
    setupRepeatables();
    setupAccountRoleForms();
    mountVueCartLines();
    mountVueCartCheckout();
    setupCartPaymentModes();
    setupCartInlineUpdates();
    setupInfiniteFeeds();
    mountVueCatalogFeedSwitchers();
    setupSupportWidget();
    setupToasts();
    setupNotificationsPoller();
});











