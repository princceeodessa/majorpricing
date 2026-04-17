<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\OneCPriceType;
use App\Models\Order;
use App\Models\Product;
use App\Services\OneC\OneCCatalogExchangeService;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

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
                $hasImport = $sortedFiles->contains(fn (array $file): bool => $file['filename'] === 'import.xml');
                $hasOffers = $sortedFiles->contains(fn (array $file): bool => $file['filename'] === 'offers.xml');
                $notes = [];

                if (! $hasImport && ! $hasOffers) {
                    $notes[] = [
                        'tone' => 'warning',
                        'text' => 'Нет import.xml: пакет не содержит каталог товаров и категорий.',
                    ];
                }

                if (! $hasImport && $hasOffers) {
                    $notes[] = [
                        'tone' => 'info',
                        'text' => 'Частичный пакет: получен только offers.xml. Это нормально для обмена только изменениями (цены, остатки, предложения).',
                    ];
                }

                if (! $hasOffers) {
                    $notes[] = [
                        'tone' => 'warning',
                        'text' => 'Нет offers.xml: цены из 1С не обновятся.',
                    ];
                }

                return [
                    'session_key' => $sessionKey,
                    'modified_timestamp' => $sortedFiles->first()['modified_timestamp'] ?? 0,
                    'modified_at' => $sortedFiles->first()['modified_at'] ?? null,
                    'files' => $sortedFiles->pluck('filename')->values()->all(),
                    'has_import' => $hasImport,
                    'has_offers' => $hasOffers,
                    'notes' => $notes,
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
            'lastImportReport' => session('onec_import_report'),
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
                ->route('admin.onec.show')
                ->with('status', 'Пакет каталога не найден. Сначала выполните выгрузку товаров из 1С.');
        }

        $result = $catalogExchangeService->import($sessionKey);

        $message = sprintf(
            'Каталог 1С импортирован вручную. Категорий: %d, товаров: %d, цен: %d, изображений: %d.',
            $result['categories'],
            $result['products'],
            $result['prices'],
            $result['images'],
        );

        if ($result['warnings'] !== []) {
            $message .= ' Причины: '.implode(' ', $result['warnings']);
        }

        return redirect()
            ->route('admin.onec.show')
            ->with('status', $message)
            ->with('onec_import_report', $result);
    }

    public function showCatalogFile(Request $request): View
    {
        $validated = $request->validate([
            'session_key' => ['required', 'string'],
            'filename' => ['required', 'string'],
        ]);

        $sessionKey = trim($validated['session_key']);
        $filename = $this->sanitizeCatalogFilename($validated['filename']);
        $uploadDir = trim((string) config('integrations.one_c.upload_dir', 'one-c-exchange'), '/');
        $relativePath = $uploadDir.'/'.$sessionKey.'/catalog/'.$filename;

        abort_unless(Storage::disk('local')->exists($relativePath), 404);

        $content = (string) Storage::disk('local')->get($relativePath);

        return view('manager.onec.file', [
            'exchangeUrl' => route('onec.exchange'),
            'sessionKey' => $sessionKey,
            'filename' => $filename,
            'content' => $content,
            'summary' => $this->summarizeXmlContent($content, $filename),
            'backUrl' => route('admin.onec.show'),
        ]);
    }

    public function downloadCatalogFile(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'session_key' => ['required', 'string'],
            'filename' => ['required', 'string'],
        ]);

        $sessionKey = trim($validated['session_key']);
        $filename = $this->sanitizeCatalogFilename($validated['filename']);
        $relativePath = $this->catalogRelativePath($sessionKey, $filename);

        abort_unless(Storage::disk('local')->exists($relativePath), 404);

        return response()->download(
            Storage::disk('local')->path($relativePath),
            basename($filename),
            ['Content-Type' => 'application/xml; charset=UTF-8']
        );
    }

    public function downloadCatalogPackage(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'session_key' => ['required', 'string'],
        ]);

        $sessionKey = trim($validated['session_key']);
        $uploadDir = trim((string) config('integrations.one_c.upload_dir', 'one-c-exchange'), '/');
        $files = collect(Storage::disk('local')->files($uploadDir.'/'.$sessionKey.'/catalog'))
            ->filter(fn (string $path): bool => in_array(basename($path), ['import.xml', 'offers.xml'], true))
            ->values();

        abort_if($files->isEmpty(), 404);
        abort_unless(class_exists(ZipArchive::class), 500, 'ZIP archive support is not available.');

        $temporaryZip = tempnam(sys_get_temp_dir(), 'onec-package-');
        abort_if($temporaryZip === false, 500, 'Unable to prepare temporary archive.');

        $zipPath = $temporaryZip.'.zip';

        if (file_exists($zipPath)) {
            @unlink($zipPath);
        }

        @rename($temporaryZip, $zipPath);

        $zip = new ZipArchive();
        abort_unless($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500, 'Unable to create package archive.');

        foreach ($files as $path) {
            $zip->addFile(Storage::disk('local')->path($path), basename($path));
        }

        $zip->close();

        return response()->download(
            $zipPath,
            'onec-catalog-'.$sessionKey.'.zip',
            ['Content-Type' => 'application/zip']
        )->deleteFileAfterSend(true);
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

    private function sanitizeCatalogFilename(string $filename): string
    {
        $normalized = str_replace('\\', '/', trim($filename));
        $segments = collect(explode('/', $normalized))
            ->filter(fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..')
            ->values();

        abort_if($segments->isEmpty(), 404);

        return $segments->implode('/');
    }

    private function catalogRelativePath(string $sessionKey, string $filename): string
    {
        $uploadDir = trim((string) config('integrations.one_c.upload_dir', 'one-c-exchange'), '/');

        return $uploadDir.'/'.$sessionKey.'/catalog/'.$filename;
    }

    /**
     * @return array{
     *     detected_type:string,
     *     categories_count:int,
     *     products_count:int,
     *     offers_count:int,
     *     price_types_count:int,
     *     category_names:array<int, string>,
     *     product_rows:array<int, array{id:string,name:string,article:string}>,
     *     offer_rows:array<int, array{id:string,price_type:string,amount:string}>,
     *     warnings:array<int, string>
     * }
     */
    private function summarizeXmlContent(string $content, string $filename): array
    {
        $summary = [
            'detected_type' => str_ends_with(mb_strtolower($filename), 'offers.xml') ? 'offers' : 'import',
            'categories_count' => 0,
            'products_count' => 0,
            'offers_count' => 0,
            'price_types_count' => 0,
            'category_names' => [],
            'product_rows' => [],
            'offer_rows' => [],
            'warnings' => [],
        ];

        $xpath = $this->createXPath($content);

        if (! $xpath) {
            $summary['warnings'][] = 'Файл не удалось разобрать как CommerceML/XML.';

            return $summary;
        }

        $categoryNodes = $this->queryChildren($xpath, '//*', null, [
            ['Группы', 'Р“СЂСѓРїРїС‹'],
            ['Группа', 'Р“СЂСѓРїРїР°'],
        ]);

        foreach ($categoryNodes as $categoryNode) {
            if (! $categoryNode instanceof DOMNode) {
                continue;
            }

            $summary['categories_count']++;
            $name = $this->firstChildValue($xpath, $categoryNode, ['Наименование', 'РќР°РёРјРµРЅРѕРІР°РЅРёРµ']);

            if (filled($name) && count($summary['category_names']) < 8) {
                $summary['category_names'][] = $name;
            }
        }

        $productNodes = $this->queryChildren($xpath, '//*', null, [
            ['Каталог', 'РљР°С‚Р°Р»РѕРі'],
            ['Товары', 'РўРѕРІР°СЂС‹'],
            ['Товар', 'РўРѕРІР°СЂ'],
        ]);

        foreach ($productNodes as $productNode) {
            if (! $productNode instanceof DOMNode) {
                continue;
            }

            $summary['products_count']++;

            if (count($summary['product_rows']) < 10) {
                $summary['product_rows'][] = [
                    'id' => $this->firstChildValue($xpath, $productNode, ['Ид', 'РРґ']) ?? '—',
                    'name' => $this->firstChildValue($xpath, $productNode, ['Наименование', 'РќР°РёРјРµРЅРѕРІР°РЅРёРµ']) ?? '—',
                    'article' => $this->firstChildValue($xpath, $productNode, ['Артикул', 'РђСЂС‚РёРєСѓР»']) ?? '—',
                ];
            }
        }

        $priceTypeNames = [];
        $priceTypeNodes = $this->queryChildren($xpath, '//*', null, [
            ['ТипыЦен', 'РўРёРїС‹Р¦РµРЅ'],
            ['ТипЦены', 'РўРёРїР¦РµРЅС‹'],
        ]);

        foreach ($priceTypeNodes as $priceTypeNode) {
            if (! $priceTypeNode instanceof DOMNode) {
                continue;
            }

            $summary['price_types_count']++;
            $id = $this->firstChildValue($xpath, $priceTypeNode, ['Ид', 'РРґ']);
            $name = $this->firstChildValue($xpath, $priceTypeNode, ['Наименование', 'РќР°РёРјРµРЅРѕРІР°РЅРёРµ']);

            if (filled($id)) {
                $priceTypeNames[$id] = $name ?: $id;
            }
        }

        $offerNodes = $this->queryChildren($xpath, '//*', null, [
            ['Предложения', 'РџСЂРµРґР»РѕР¶РµРЅРёСЏ'],
            ['Предложение', 'РџСЂРµРґР»РѕР¶РµРЅРёРµ'],
        ]);

        foreach ($offerNodes as $offerNode) {
            if (! $offerNode instanceof DOMNode) {
                continue;
            }

            $summary['offers_count']++;

            if (count($summary['offer_rows']) >= 10) {
                continue;
            }

            $priceNode = $this->queryChildren($xpath, '.', $offerNode, [
                ['Цены', 'Р¦РµРЅС‹'],
                ['Цена', 'Р¦РµРЅР°'],
            ])->item(0);

            $priceTypeId = $priceNode instanceof DOMNode
                ? $this->firstChildValue($xpath, $priceNode, ['ИдТипаЦены', 'РРґРўРёРїР°Р¦РµРЅС‹'])
                : null;

            $summary['offer_rows'][] = [
                'id' => $this->firstChildValue($xpath, $offerNode, ['Ид', 'РРґ']) ?? '—',
                'price_type' => $priceTypeId ? ($priceTypeNames[$priceTypeId] ?? $priceTypeId) : '—',
                'amount' => $priceNode instanceof DOMNode
                    ? ($this->firstChildValue($xpath, $priceNode, ['ЦенаЗаЕдиницу', 'Р¦РµРЅР°Р—Р°Р•РґРёРЅРёС†Сѓ']) ?? '—')
                    : '—',
            ];
        }

        if ($summary['products_count'] === 0 && $summary['offers_count'] > 0) {
            $summary['warnings'][] = 'В файле есть только предложения и цены. Для импорта товаров нужен import.xml.';
        }

        if ($summary['products_count'] > 0 && $summary['offers_count'] === 0 && str_contains(mb_strtolower($filename), 'offers')) {
            $summary['warnings'][] = 'Файл называется offers.xml, но предложения в нем не найдены.';
        }

        return $summary;
    }

    private function createXPath(string $xml): ?DOMXPath
    {
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml) ?? $xml;
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;

        if (! @$document->loadXML($xml, LIBXML_NOCDATA | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            return null;
        }

        return new DOMXPath($document);
    }

    /**
     * @param  array<int, array<int, string>>  $levels
     */
    private function queryChildren(DOMXPath $xpath, string $base, ?DOMNode $contextNode, array $levels)
    {
        $query = $base;

        foreach ($levels as $index => $level) {
            $segment = '*['.$this->localNamePredicate($level).']';

            if ($index === 0 && $base === '//*') {
                $query .= '['.$this->localNamePredicate($level).']';
                continue;
            }

            $query .= '/'.$segment;
        }

        return $xpath->query($query, $contextNode);
    }

    /**
     * @param  array<int, string>|string  $localNames
     */
    private function firstChildValue(DOMXPath $xpath, DOMNode $contextNode, array|string $localNames): ?string
    {
        $names = is_array($localNames) ? $localNames : [$localNames];
        $node = $xpath->query('./*['.$this->localNamePredicate($names).']', $contextNode)->item(0);

        return $node ? trim($node->textContent) : null;
    }

    /**
     * @param  array<int, string>  $localNames
     */
    private function localNamePredicate(array $localNames): string
    {
        return collect($localNames)
            ->map(fn (string $name): string => 'local-name()="'.$name.'"')
            ->implode(' or ');
    }
}
