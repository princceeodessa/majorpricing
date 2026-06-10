<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::query()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        return view('admin.banners.index', compact('banners'));
    }

    public function create()
    {
        return view('admin.banners.edit', [
            'banner' => new Banner(['is_active' => true, 'sort_order' => 100]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['image_path'] = $this->saveImage($request, null);
        Banner::create($data);

        return redirect()->route('admin.banners.index')->with('status', 'Баннер создан');
    }

    public function edit(Banner $banner)
    {
        return view('admin.banners.edit', compact('banner'));
    }

    public function update(Request $request, Banner $banner)
    {
        $data = $this->validated($request);
        $newImage = $this->saveImage($request, $banner->image_path);
        if ($newImage !== null) {
            $data['image_path'] = $newImage;
        }
        $banner->update($data);

        return redirect()->route('admin.banners.index')->with('status', 'Баннер обновлён');
    }

    public function destroy(Banner $banner)
    {
        if ($banner->image_path && Storage::disk('public')->exists($banner->image_path)) {
            Storage::disk('public')->delete($banner->image_path);
        }
        $banner->delete();

        return redirect()->route('admin.banners.index')->with('status', 'Баннер удалён');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'link_url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'placeholder_color' => ['nullable', 'string', 'max:255'],
            'placeholder_text' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function saveImage(Request $request, ?string $existing): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }
        if ($existing && Storage::disk('public')->exists($existing)) {
            Storage::disk('public')->delete($existing);
        }
        $path = $request->file('image')->store('banners', 'public');
        return $path;
    }
}
