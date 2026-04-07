<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\OneCPriceType;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
