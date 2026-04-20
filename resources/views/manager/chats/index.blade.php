@extends('layouts.app')

@section('title', 'Чаты клиентов - ПОТОЛКОВЫЧ')

@section('content')
    @php($currentUser = auth()->user())

    <section class="catalog-chat-page">
        <div class="catalog-page-head">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Клиенты</p>
                <h1 class="mt-2 font-['IBM_Plex_Sans'] text-4xl font-semibold text-slate-950">Чаты клиентов</h1>
            </div>
            <p class="max-w-xl text-sm leading-6 text-slate-600">
                Отдельная рабочая лента обращений. Менеджер видит только своих клиентов, админ видит все диалоги.
            </p>
        </div>

        <div class="catalog-chat-shell surface-card">
            <aside class="catalog-chat-sidebar">
                <div class="catalog-chat-sidebar__head">
                    <span>Диалоги</span>
                    <strong>{{ $clients->count() }}</strong>
                </div>

                <div class="catalog-chat-list">
                    @forelse ($clients as $client)
                        @php($lastMessage = $client->supportMessages->last())
                        @php($isActive = $activeClient && $activeClient->id === $client->id)

                        <a
                            href="{{ route('manager.chats.index', ['client' => $client]) }}"
                            class="catalog-chat-list__item {{ $isActive ? 'is-active' : '' }}"
                        >
                            <span class="catalog-chat-list__avatar">{{ mb_substr($client->name, 0, 1) }}</span>
                            <span class="catalog-chat-list__body">
                                <span class="catalog-chat-list__name">{{ $client->name }}</span>
                                <span class="catalog-chat-list__company">{{ $client->company ?: $client->login }}</span>
                                <span class="catalog-chat-list__last">
                                    {{ $lastMessage ? \Illuminate\Support\Str::limit($lastMessage->message, 58) : 'Сообщений пока нет' }}
                                </span>
                            </span>
                            @if ($lastMessage)
                                <time class="catalog-chat-list__time" datetime="{{ $lastMessage->created_at?->toIso8601String() }}">
                                    {{ $lastMessage->created_at?->format('d.m H:i') }}
                                </time>
                            @endif
                        </a>
                    @empty
                        <div class="catalog-chat-empty">
                            Клиенты пока не добавлены.
                        </div>
                    @endforelse
                </div>
            </aside>

            <section class="catalog-chat-dialog">
                @if ($activeClient)
                    <header class="catalog-chat-dialog__head">
                        <div>
                            <span class="soft-badge">Диалог</span>
                            <h2>{{ $activeClient->name }}</h2>
                            <p>{{ $activeClient->company ?: $activeClient->login }}</p>
                        </div>
                        <a href="{{ route('orders.index') }}" class="ghost-button">Заказы</a>
                    </header>

                    <div class="catalog-chat-thread">
                        @forelse ($messages as $message)
                            @php($isOwn = (int) $message->sender_id === (int) $currentUser->id)

                            <article class="catalog-chat-message {{ $isOwn ? 'is-own' : 'is-peer' }}">
                                <p>{{ $message->message }}</p>
                                <footer>
                                    <span>{{ $isOwn ? 'Вы' : ($message->sender?->name ?? $activeClient->name) }}</span>
                                    <span class="catalog-message-meta">
                                        <time datetime="{{ $message->created_at?->toIso8601String() }}">
                                            {{ $message->created_at?->format('d.m.Y H:i') }}
                                        </time>
                                        @if ($isOwn)
                                            <span
                                                class="catalog-message-checks {{ $message->read_at ? 'is-read' : '' }}"
                                                title="{{ $message->read_at ? 'Прочитано' : 'Отправлено' }}"
                                                aria-label="{{ $message->read_at ? 'Прочитано' : 'Отправлено' }}"
                                            >
                                                {{ $message->read_at ? '✓✓' : '✓' }}
                                            </span>
                                        @endif
                                    </span>
                                </footer>
                            </article>
                        @empty
                            <div class="catalog-chat-empty">
                                Переписка с клиентом пока пустая.
                            </div>
                        @endforelse
                    </div>

                    <form action="{{ route('manager.support.messages.store') }}" method="POST" class="catalog-chat-form">
                        @csrf
                        <input type="hidden" name="client_id" value="{{ $activeClient->id }}">
                        <input type="hidden" name="redirect_to" value="chats">
                        <textarea name="message" rows="3" placeholder="Напишите ответ клиенту"></textarea>
                        <button type="submit" class="action-button">Отправить</button>
                    </form>
                @else
                    <div class="catalog-chat-dialog__empty">
                        <h2>Диалог не выбран</h2>
                        <p>Когда менеджер добавит клиентов, здесь появятся чаты.</p>
                    </div>
                @endif
            </section>
        </div>
    </section>
@endsection
