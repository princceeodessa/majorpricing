@extends('layouts.app')

@section('title', ($banner->exists ? 'Редактирование баннера' : 'Новый баннер') . ' · Админка')

@section('content')
<style>
    .mj-admb-edit, .mj-admb-edit *, .mj-admb-edit *::before, .mj-admb-edit *::after { box-sizing: border-box; }
    .mj-admb-edit { padding: 1.5rem 1rem 3rem; max-width: 720px; margin: 0 auto; }
    .mj-admb-edit__title { margin: 0 0 1.25rem; font: 800 clamp(20px, 4vw, 26px)/1.2 'IBM Plex Sans', system-ui, sans-serif; color: #0b1220; letter-spacing: -0.01em; }
    .mj-admb-edit__form { display: grid; gap: 16px; background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 20px; }
    .mj-admb-edit__field { display: grid; gap: 6px; }
    .mj-admb-edit__label { font-size: 13px; font-weight: 700; color: #0b1220; }
    .mj-admb-edit__hint { font-size: 12px; color: #64748b; }
    .mj-admb-edit__input,
    .mj-admb-edit__textarea {
        width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 10px;
        font: 500 14px/1.4 system-ui, sans-serif; color: #0b1220; background: #f9fafb;
        outline: none; transition: border-color .15s, background .15s;
    }
    .mj-admb-edit__input:focus,
    .mj-admb-edit__textarea:focus { border-color: #d60000; background: #fff; }
    .mj-admb-edit__row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 540px) { .mj-admb-edit__row { grid-template-columns: 1fr; } }
    .mj-admb-edit__check { display: flex; align-items: center; gap: 8px; font-size: 14px; }
    .mj-admb-edit__actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 6px; }
    .mj-admb-edit__btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; background: #d60000; color: #fff; text-decoration: none; font-weight: 700; font-size: 14px; border: 0; cursor: pointer; }
    .mj-admb-edit__btn--ghost { background: transparent; color: #64748b; border: 1px solid #e5e7eb; }
    .mj-admb-edit__preview {
        aspect-ratio: 2 / 1; border-radius: 12px; overflow: hidden; background: #f6f7fa;
        position: relative; display: flex; align-items: center; justify-content: center;
    }
    .mj-admb-edit__preview img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .mj-admb-edit__errors { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px 14px; border-radius: 10px; font-size: 13px; }
    .mj-admb-edit__errors ul { margin: 4px 0 0 0; padding-left: 18px; }
</style>

<div class="mj-admb-edit">
    <h1 class="mj-admb-edit__title">{{ $banner->exists ? 'Редактирование баннера' : 'Новый баннер' }}</h1>

    @if ($errors->any())
        <div class="mj-admb-edit__errors">
            Проверь поля:
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        class="mj-admb-edit__form"
        method="POST"
        action="{{ $banner->exists ? route('admin.banners.update', $banner) : route('admin.banners.store') }}"
        enctype="multipart/form-data"
    >
        @csrf
        @if ($banner->exists)
            @method('PUT')
        @endif

        <div class="mj-admb-edit__field">
            <label class="mj-admb-edit__label" for="title">Название (служебное)</label>
            <input id="title" type="text" name="title" required maxlength="255" class="mj-admb-edit__input" value="{{ old('title', $banner->title) }}">
        </div>

        <div class="mj-admb-edit__field">
            <label class="mj-admb-edit__label" for="image">Картинка (jpg/png/webp, до 5 MB)</label>
            <input id="image" type="file" name="image" accept="image/*" class="mj-admb-edit__input">
            <span class="mj-admb-edit__hint">Если не загружать — останется заглушка ниже. Соотношение 2:1, рекомендуется 1200×600 или больше.</span>
            @if ($banner->image_path)
                <div class="mj-admb-edit__preview" style="margin-top: 8px;">
                    <img src="{{ $banner->imageUrl() }}" alt="Текущая картинка">
                </div>
            @endif
        </div>

        <div class="mj-admb-edit__field">
            <label class="mj-admb-edit__label" for="link_url">Куда ведёт тап (URL, опционально)</label>
            <input id="link_url" type="text" name="link_url" maxlength="500" class="mj-admb-edit__input" value="{{ old('link_url', $banner->link_url) }}" placeholder="/catalog?category=svetilniki или https://example.com">
        </div>

        <fieldset class="mj-admb-edit__field" style="border:0; padding:0; margin:0">
            <legend class="mj-admb-edit__label">Если картинки нет — параметры заглушки</legend>
            <div class="mj-admb-edit__row">
                <div class="mj-admb-edit__field">
                    <label class="mj-admb-edit__hint" for="placeholder_color">Цвет/градиент CSS</label>
                    <input id="placeholder_color" type="text" name="placeholder_color" maxlength="255" class="mj-admb-edit__input" value="{{ old('placeholder_color', $banner->placeholder_color) }}" placeholder="linear-gradient(135deg, #d60000 0%, #b40d12 100%)">
                </div>
                <div class="mj-admb-edit__field">
                    <label class="mj-admb-edit__hint" for="placeholder_text">Текст</label>
                    <input id="placeholder_text" type="text" name="placeholder_text" maxlength="255" class="mj-admb-edit__input" value="{{ old('placeholder_text', $banner->placeholder_text) }}" placeholder="Акции и скидки">
                </div>
            </div>
        </fieldset>

        <div class="mj-admb-edit__row">
            <div class="mj-admb-edit__field">
                <label class="mj-admb-edit__label" for="sort_order">Порядок показа</label>
                <input id="sort_order" type="number" name="sort_order" min="0" max="9999" required class="mj-admb-edit__input" value="{{ old('sort_order', $banner->sort_order) }}">
                <span class="mj-admb-edit__hint">Меньше число → раньше показан</span>
            </div>
            <div class="mj-admb-edit__field" style="justify-content: end">
                <label class="mj-admb-edit__check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $banner->is_active) ? 'checked' : '' }}>
                    Активен (показывать на сайте)
                </label>
            </div>
        </div>

        <div class="mj-admb-edit__actions">
            <button type="submit" class="mj-admb-edit__btn">Сохранить</button>
            <a href="{{ route('admin.banners.index') }}" class="mj-admb-edit__btn mj-admb-edit__btn--ghost">Отмена</a>
        </div>
    </form>
</div>
@endsection
