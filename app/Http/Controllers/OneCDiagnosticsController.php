<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\OneCPriceType;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\OneC\OneCCatalogExchangeService;

class OneCDiagnosticsController extends Controller
{
    public function show(Request $request): View
    {
        $uploadDir = trim((string) config('integrations.one_c.upload_dir', 'one-c-exchange'), '/');
        $recentFiles = collect(Storage::disk('local')->allFiles($uploadDir))
            ->map(function (string $path): array {
                $segments = collect(explode('/', trim($path, '/')))->values();
                $type = $segments->count() >= 3 ? $segments[$segments->count() - 2] : null;
                $sessionKey = $segments->count() >= 4 ? $segments[$segments->count() - 3] : null;

                return [
                    'path' => $path,
                    'filename' => basename($path),
                    'type' => $type,
                    'session_key' => $sessionKey,
                    'modified_timestamp' => Storage::disk('local')->lastModified($path),
                    'modified_at' => date('d.m.Y H:i:s', Storage::disk('local')->lastModified($path)),
                    'size' => $this->humanFileSize((int) Storage::disk('local')->size($path)),
                ];
            })
            ->sortByDesc('modified_timestamp')
            ->take(20)
            ->values();

        $catalogPackages = $recentFiles
            ->where('type', 'catalog')
            ->groupBy('session_key')
            ->map(function ($files, string $sessionKey): array {
                $sortedFiles = collect($files)->sortByDesc('modified_timestamp')->values();

                return [
                    'session_key' => $sessionKey,
                    'modified_timestamp' => $sortedFiles->first()['modified_timestamp'] ?? 0,
                    'modified_at' => $sortedFiles->first()['modified_at'] ?? null,
                    'files' => $sortedFiles->pluck('filename')->values()->all(),
                    'has_import' => $sortedFiles->contains(fn (array $file): bool => $file['filename'] === 'import.xml'),
                    'has_offers' => $sortedFiles->contains(fn (array $file): bool => $file['filename'] === 'offers.xml'),
                ];
            })
            ->sortByDesc('modified_timestamp')
            ->values();

        return view('manager.onec.show', [
            'exchangeUrl' => route('onec.exchange'),
            'fallbackExchangeUrl' => url('/1c_exchange.php'),
            'oneCSettings' => [
                'username' => (string) config('integrations.one_c.username'),
                'password_configured' => filled((string) config('integrations.one_c.password')),
                'file_limit' => $this->humanFileSize((int) config('integrations.one_c.file_limit', 10 * 1024 * 1024)),
                'upload_dir' => $uploadDir,
            ],
            'stats' => [
                'categories' => Category::query()->whereNotNull('one_c_id')->count(),
                'products' => Product::query()->whereNotNull('one_c_id')->count(),
                'price_types' => OneCPriceType::query()->count(),
                'orders_pending_export' => Order::query()->whereNull('one_c_exported_at')->count(),
                'orders_exported' => Order::query()->whereNotNull('one_c_exported_at')->count(),
                'orders_with_document' => Order::query()->whereNotNull('one_c_document_id')->count(),
            ],
            'recentFiles' => $recentFiles,
            'catalogPackages' => $catalogPackages,
            'recentProducts' => Product::query()
                ->whereNotNull('one_c_id')
                ->latest('updated_at')
                ->take(8)
                ->get(['id', 'title', 'one_c_id', 'vendor_code', 'price_from', 'updated_at']),
            'recentOrders' => Order::query()
                ->latest('updated_at')
                ->take(8)
                ->get(['id', 'number', 'status', 'payment_status', 'one_c_document_id', 'one_c_exported_at', 'updated_at']),
        ]);
    }

    public function importCatalog(Request $request, OneCCatalogExchangeService $catalogExchangeService): RedirectResponse
    {
        $validated = $request->validate([
            'session_key' => ['required', 'string'],
        ]);

        $sessionKey = trim($validated['session_key']);
        $uploadDir = trim((string) config('integrations.one_c.upload_dir', 'one-c-exchange'), '/');
        $files = collect(Storage::disk('local')->allFiles($uploadDir))
            ->filter(fn (string $path): bool => str_contains($path, '/'.$sessionKey.'/catalog/'))
            ->values();

        if ($files->isEmpty()) {
            return redirect()
                ->route('manager.onec.show')
                ->with('status', 'Пакет каталога не найден. Сначала выполните выгрузку товаров из 1С.');
        }

        $result = $catalogExchangeService->import($sessionKey);

        return redirect()
            ->route('manager.onec.show')
            ->with('status', sprintf(
                'Каталог 1С импортирован вручную. Категорий: %d, товаров: %d, цен: %d, изображений: %d.',
                $result['categories'],
                $result['products'],
                $result['prices'],
                $result['images'],
            ));
    }

    private function humanFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB'];
        $value = $bytes / 1024;

        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'GB') {
                return number_format($value, $value >= 100 ? 0 : 1, ',', ' ').' '.$unit;
            }

            $value /= 1024;
        }

        return (string) $bytes;
    }
}
