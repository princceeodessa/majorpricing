@if (($supportWidgetEnabled ?? false) && ($supportWidgetManager ?? null))
    @php
        $manager = $supportWidgetManager;
        $managerName = trim((string) ($manager->name ?: 'Ваш менеджер'));
        $managerInitials = collect(preg_split('/\s+/u', $managerName))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_substr($part, 0, 1))
            ->implode('');
        $threadMessages = $supportWidgetMessages ?? collect();
        $widgetShouldOpen = $errors->has('message') ? '1' : '0';
        $managerSubtitle = $manager->phone ?: ($manager->email ?: 'Персональный менеджер');
    @endphp

    <button
        type="button"
        class="catalog-support-widget-trigger"
        data-support-widget-open
        aria-controls="support-widget-dialog"
        aria-label="Написать менеджеру"
    >
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M4.5 6.75A3.25 3.25 0 0 1 7.75 3.5h8.5a3.25 3.25 0 0 1 3.25 3.25v6a3.25 3.25 0 0 1-3.25 3.25H11.4l-4.9 4.1v-4.1H7.75A3.25 3.25 0 0 1 4.5 12.75Z" />
        </svg>
    </button>

    <div
        class="catalog-support-widget hidden"
        data-support-widget
        data-support-widget-default-open="{{ $widgetShouldOpen }}"
        data-support-widget-read-url="{{ route('account.support.messages.read') }}"
    >
        <div class="catalog-support-widget__backdrop" data-support-widget-close></div>

        <section
            id="support-widget-dialog"
            class="catalog-support-widget__dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="support-widget-title"
            tabindex="-1"
        >
            <header class="catalog-support-widget__header">
                <div class="catalog-support-widget__manager">
                    <div class="catalog-support-widget__avatar">{{ $managerInitials !== '' ? $managerInitials : 'М' }}</div>
                    <div>
                        <span class="catalog-support-widget__eyebrow">Вопрос менеджеру</span>
                        <h2 id="support-widget-title">{{ $managerName }}</h2>
                        <p>{{ $managerSubtitle }}</p>
                    </div>
                </div>

                <button type="button" class="catalog-support-widget__close" data-support-widget-close aria-label="Закрыть чат">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </header>

            <div class="catalog-support-widget__body" data-support-widget-body>
                @forelse ($threadMessages as $threadMessage)
                    @php($isOwnMessage = (int) $threadMessage->sender_id === (int) auth()->id())
                    <article class="catalog-support-widget__message {{ $isOwnMessage ? 'is-own' : 'is-peer' }}">
                        <div class="catalog-support-widget__bubble">
                            <p>{{ $threadMessage->message }}</p>
                            <footer>
                                <span>{{ $isOwnMessage ? 'Вы' : $managerName }}</span>
                                <span class="catalog-message-meta">
                                    <time datetime="{{ $threadMessage->created_at?->toIso8601String() }}">{{ $threadMessage->created_at?->format('d.m.Y H:i') }}</time>
                                    @if ($isOwnMessage)
                                        <span
                                            class="catalog-message-checks {{ $threadMessage->read_at ? 'is-read' : '' }}"
                                            title="{{ $threadMessage->read_at ? 'Прочитано' : 'Отправлено' }}"
                                            aria-label="{{ $threadMessage->read_at ? 'Прочитано' : 'Отправлено' }}"
                                        >
                                            {{ $threadMessage->read_at ? '✓✓' : '✓' }}
                                        </span>
                                    @endif
                                </span>
                            </footer>
                        </div>
                    </article>
                @empty
                    <div class="catalog-support-widget__empty">
                        <p>Здесь будет переписка с вашим менеджером.</p>
                    </div>
                @endforelse
            </div>

            <form
                action="{{ route('account.support.messages.store') }}"
                method="POST"
                class="catalog-support-widget__form"
                data-support-widget-form
            >
                @csrf
                <label class="sr-only" for="support-widget-message">Сообщение менеджеру</label>
                <textarea
                    id="support-widget-message"
                    name="message"
                    rows="4"
                    placeholder="Напишите вопрос менеджеру"
                >{{ old('message') }}</textarea>

                <div class="catalog-support-widget__form-footer">
                    @error('message')
                        <p class="catalog-support-widget__error" data-support-widget-feedback>{{ $message }}</p>
                    @else
                        <p class="catalog-support-widget__hint" data-support-widget-feedback>Ответ придет в этот чат и будет виден менеджеру в его рабочем окне.</p>
                    @enderror

                    <button type="submit" class="catalog-support-widget__submit" data-support-widget-submit>
                        <span class="catalog-support-widget__submit-label">Отправить</span>
                        <span class="catalog-support-widget__submit-loader" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </section>
    </div>
@endif
