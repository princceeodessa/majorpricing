@extends('layouts.app')

@section('title', 'Заявка на регистрацию | МАЖОР')

@section('content')
    @php($contactPeople = old('contact_people'))
    @php($messengers = old('messengers'))
    @php($contactPeople = is_array($contactPeople) && count(array_filter($contactPeople, fn ($item) => filled($item))) > 0 ? array_values($contactPeople) : [''])
    @php($messengers = is_array($messengers) && count(array_filter($messengers, fn ($item) => filled($item))) > 0 ? array_values($messengers) : [''])

    <div class="catalog-auth-screen">
        <section class="surface-card reveal-card catalog-auth-panel">
            <div class="catalog-auth-panel__inner catalog-auth-panel__inner--wide">
                <div class="catalog-page-head">
                    <div>
                        <span class="soft-badge">Регистрация</span>
                        <h1 class="catalog-auth-panel__title mt-4">Заявка на доступ</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
                            Заполните данные компании и контакты. После проверки менеджер подтвердит регистрацию и откроет доступ в кабинет.
                        </p>
                    </div>

                    <a href="{{ route('login') }}" class="ghost-button">Уже есть доступ</a>
                </div>

                <form action="{{ route('registration-requests.store') }}" method="POST" class="access-user-create__form mt-8">
                    @csrf

                    <div class="access-user-create__grid">
                        <label class="access-field">
                            <span>Имя</span>
                            <input type="text" name="name" value="{{ old('name') }}" placeholder="Иван Петров">
                        </label>

                        <label class="access-field">
                            <span>Компания</span>
                            <input type="text" name="company" value="{{ old('company') }}" placeholder="ООО МАЖОР">
                        </label>

                        <label class="access-field">
                            <span>Телефон</span>
                            <input type="text" name="phone" value="{{ old('phone') }}" placeholder="+7 999 000-00-00">
                        </label>

                        <label class="access-field">
                            <span>Логин</span>
                            <input type="text" name="login" value="{{ old('login') }}" placeholder="major_client">
                        </label>

                        <label class="access-field">
                            <span>Email</span>
                            <input type="email" name="email" value="{{ old('email') }}" placeholder="client@example.com">
                        </label>

                        <label class="access-field">
                            <span>Пароль</span>
                            <input type="password" name="password" placeholder="Минимум 8 символов">
                        </label>

                        <label class="access-field">
                            <span>Подтверждение пароля</span>
                            <input type="password" name="password_confirmation" placeholder="Повторите пароль">
                        </label>

                        <div class="access-field access-field--full catalog-repeatable" data-repeatable>
                            <div class="catalog-repeatable__head">
                                <span>Контактные лица</span>
                                <button type="button" class="catalog-inline-action" data-repeatable-add>+ Добавить</button>
                            </div>

                            <div class="catalog-repeatable__items" data-repeatable-items>
                                @foreach ($contactPeople as $contactPerson)
                                    <div class="catalog-repeatable__item" data-repeatable-row>
                                        <input
                                            type="text"
                                            name="contact_people[]"
                                            value="{{ $contactPerson }}"
                                            placeholder="Контакт по заявкам"
                                            class="catalog-clean-input"
                                        >
                                        <button type="button" class="catalog-inline-action" data-repeatable-remove>Удалить</button>
                                    </div>
                                @endforeach
                            </div>

                            <template data-repeatable-template>
                                <div class="catalog-repeatable__item" data-repeatable-row>
                                    <input type="text" name="contact_people[]" value="" placeholder="Контакт по заявкам" class="catalog-clean-input">
                                    <button type="button" class="catalog-inline-action" data-repeatable-remove>Удалить</button>
                                </div>
                            </template>
                        </div>

                        <div class="access-field access-field--full catalog-repeatable" data-repeatable>
                            <div class="catalog-repeatable__head">
                                <span>Мессенджеры</span>
                                <button type="button" class="catalog-inline-action" data-repeatable-add>+ Добавить</button>
                            </div>

                            <div class="catalog-repeatable__items" data-repeatable-items>
                                @foreach ($messengers as $messenger)
                                    <div class="catalog-repeatable__item" data-repeatable-row>
                                        <input
                                            type="text"
                                            name="messengers[]"
                                            value="{{ $messenger }}"
                                            placeholder="@company_support или WhatsApp"
                                            class="catalog-clean-input"
                                        >
                                        <button type="button" class="catalog-inline-action" data-repeatable-remove>Удалить</button>
                                    </div>
                                @endforeach
                            </div>

                            <template data-repeatable-template>
                                <div class="catalog-repeatable__item" data-repeatable-row>
                                    <input type="text" name="messengers[]" value="" placeholder="@company_support или WhatsApp" class="catalog-clean-input">
                                    <button type="button" class="catalog-inline-action" data-repeatable-remove>Удалить</button>
                                </div>
                            </template>
                        </div>

                        <label class="access-field access-field--full">
                            <span>Адрес</span>
                            <input type="text" name="delivery_address" value="{{ old('delivery_address') }}" placeholder="Город, улица, объект, комментарий по доставке">
                        </label>
                    </div>

                    <button type="submit" class="action-button access-user-create__submit">Отправить заявку</button>
                </form>
            </div>
        </section>
    </div>
@endsection
