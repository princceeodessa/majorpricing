@extends('layouts.app')

@section('title', 'Баннеры · Админка')

@section('content')
<style>
    .mj-admb, .mj-admb *, .mj-admb *::before, .mj-admb *::after { box-sizing: border-box; }
    .mj-admb { padding: 1.5rem 1rem; max-width: 1100px; margin: 0 auto; }
    .mj-admb__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 1.25rem; flex-wrap: wrap; }
    .mj-admb__title { margin: 0; font: 800 clamp(20px, 4vw, 26px)/1.2 'IBM Plex Sans', system-ui, sans-serif; color: #0b1220; letter-spacing: -0.01em; }
    .mj-admb__btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; background: #d60000; color: #fff; text-decoration: none; font-weight: 700; font-size: 14px; border: 0; cursor: pointer; }
    .mj-admb__btn:hover { background: #b40d12; }
    .mj-admb__btn--ghost { background: transparent; color: #d60000; border: 1px solid #d60000; }
    .mj-admb__btn--danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    .mj-admb__status { padding: 10px 14px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; border-radius: 10px; margin-bottom: 1rem; font-size: 14px; }
    .mj-admb__list { display: grid; grid-template-columns: 1fr; gap: 12px; }
    @media (min-width: 768px) { .mj-admb__list { grid-template-columns: repeat(2, 1fr); } }
    @media (min-width: 1024px) { .mj-admb__list { grid-template-columns: repeat(3, 1fr); } }
    .mj-admb__card { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05); }
    .mj-admb__preview { aspect-ratio: 2 / 1; background: #f6f7fa; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; }
    .mj-admb__preview img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .mj-admb__placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 18px; padding: 14px; text-align: center; text-shadow: 0 1px 2px rgba(0,0,0,0.2); }
    .mj-admb__body { padding: 12px 14px; }
    .mj-admb__name { font-weight: 700; font-size: 15px; color: #0b1220; margin-bottom: 6px; word-break: break-word; }
    .mj-admb__meta { font-size: 12px; color: #64748b; margin-bottom: 10px; display: flex; flex-wrap: wrap; gap: 8px; }
    .mj-admb__chip { padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
    .mj-admb__chip--active { background: #ecfdf5; color: #047857; }
    .mj-admb__chip--inactive { background: #fef2f2; color: #b91c1c; }
    .mj-admb__actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .mj-admb__empty { padding: 40px 20px; text-align: center; color: #64748b; }
</style>

<div class="mj-admb">
    <div class="mj-admb__head">
        <h1 class="mj-admb__title">Баннеры главной</h1>
        <a href="{{ route('admin.banners.create') }}" class="mj-admb__btn">+ Новый баннер</a>
    </div>

    @if (session('status'))
        <div class="mj-admb__status">{{ session('status') }}</div>
    @endif

    @if ($banners->isEmpty())
        <div class="mj-admb__empty">Баннеров пока нет. Нажми «Новый баннер», чтобы добавить первый.</div>
    @else
        <div class="mj-admb__list">
            @foreach ($banners as $banner)
                <div class="mj-admb__card">
                    <div class="mj-admb__preview">
                        @if ($banner->imageUrl())
                            <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->title }}">
                        @else
                            <div class="mj-admb__placeholder" style="background: {{ $banner->placeholder_color ?: 'linear-gradient(135deg,#d60000,#b40d12)' }}">
                                {{ $banner->placeholder_text ?: $banner->title }}
                            </div>
                        @endif
                    </div>
                    <div class="mj-admb__body">
                        <div class="mj-admb__name">{{ $banner->title }}</div>
                        <div class="mj-admb__meta">
                            <span class="mj-admb__chip {{ $banner->is_active ? 'mj-admb__chip--active' : 'mj-admb__chip--inactive' }}">
                                {{ $banner->is_active ? 'Активен' : 'Скрыт' }}
                            </span>
                            <span>Порядок: {{ $banner->sort_order }}</span>
                            @if ($banner->link_url)
                                <span>→ {{ \Illuminate\Support\Str::limit($banner->link_url, 32) }}</span>
                            @endif
                        </div>
                        <div class="mj-admb__actions">
                            <a href="{{ route('admin.banners.edit', $banner) }}" class="mj-admb__btn mj-admb__btn--ghost">Редактировать</a>
                            <form action="{{ route('admin.banners.destroy', $banner) }}" method="POST" onsubmit="return confirm('Удалить баннер?');" style="display:inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="mj-admb__btn mj-admb__btn--danger">Удалить</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
