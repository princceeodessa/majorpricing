<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SplFileInfo;

#[Signature('catalog:import-figma-images {path : Path to directory with exported Figma product images} {--overwrite : Replace existing product images} {--dry-run : Show matches without writing files} {--apply-ambiguous : Apply image to all matched variants when ambiguous}')]
#[Description('Imports exported Figma product images and matches them to catalog products by code or title')]
class ImportFigmaProductImagesCommand extends Command
{
    /**
     * @var list<string>
     */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'svg'];

    public function handle(): int
    {
        $sourcePath = $this->resolvePath((string) $this->argument('path'));

        if (! is_dir($sourcePath)) {
            $this->components->error("Каталог с изображениями не найден: {$sourcePath}");

            return self::FAILURE;
        }

        $files = collect(File::allFiles($sourcePath))
            ->filter(fn (SplFileInfo $file): bool => in_array(mb_strtolower($file->getExtension()), self::IMAGE_EXTENSIONS, true))
            ->sortBy(fn (SplFileInfo $file): string => $this->stableSortKey($file, $sourcePath))
            ->values();

        if ($files->isEmpty()) {
            $this->components->error("В каталоге {$sourcePath} нет изображений поддерживаемых форматов.");

            return self::FAILURE;
        }

        $products = Product::query()
            ->select(['id', 'slug', 'title', 'name', 'one_c_id', 'one_c_code', 'vendor_code', 'image_path'])
            ->with('productImages')
            ->get();

        $exactIndex = $this->buildExactIndex($products);
        $targetDirectory = public_path('catalog-media/figma');

        if (! $this->option('dry-run')) {
            File::ensureDirectoryExists($targetDirectory);
        }

        $matched = 0;
        $updated = 0;
        $skipped = 0;
        $resolvedAmbiguous = 0;
        $unmatched = [];
        $ambiguous = [];
        $clearedProductIds = [];

        foreach ($files as $file) {
            $matched++;
            $product = $this->resolveProductMatch($file, $sourcePath, $products, $exactIndex);
            $relativePath = $this->relativePath($file, $sourcePath);

            if ($product === null) {
                $unmatched[] = $relativePath;
                continue;
            }

            if ($product instanceof Collection) {
                if ($this->option('apply-ambiguous')) {
                    foreach ($product as $candidate) {
                        $attached = $this->attachImageToProduct(
                            $candidate,
                            $file,
                            $sourcePath,
                            $targetDirectory,
                            $clearedProductIds,
                        );

                        if ($attached) {
                            $updated++;
                            $resolvedAmbiguous++;
                        } else {
                            $skipped++;
                        }
                    }

                    continue;
                }

                $ambiguous[] = [
                    'file' => $relativePath,
                    'products' => $product
                        ->take(4)
                        ->map(fn (Product $candidate): string => "#{$candidate->id} ".$candidate->publicTitle())
                        ->implode('; '),
                ];
                continue;
            }

            $attached = $this->attachImageToProduct(
                $product,
                $file,
                $sourcePath,
                $targetDirectory,
                $clearedProductIds,
            );

            if ($attached) {
                $updated++;
            } else {
                $skipped++;
            }
        }

        $this->table(
            ['Показатель', 'Значение'],
            [
                ['Файлов найдено', $files->count()],
                ['Файлов обработано', $matched],
                ['Изображений привязано', $updated],
                ['Привязано по неоднозначным', $resolvedAmbiguous],
                ['Пропущено (уже была картинка)', $skipped],
                ['Не сопоставлено', count($unmatched)],
                ['Неоднозначных совпадений', count($ambiguous)],
            ],
        );

        if ($unmatched !== []) {
            $this->newLine();
            $this->components->warn('Не сопоставлены:');
            foreach (array_slice($unmatched, 0, 20) as $fileName) {
                $this->line(' - '.$fileName);
            }
        }

        if ($ambiguous !== []) {
            $this->newLine();
            $this->components->warn('Неоднозначные совпадения:');
            foreach (array_slice($ambiguous, 0, 20) as $row) {
                $this->line(' - '.$row['file'].' => '.$row['products']);
            }
        }

        $this->newLine();
        $this->components->info($this->option('dry-run')
            ? 'Dry-run завершен. Файлы не копировались.'
            : 'Импорт изображений из Figma завершен.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, int>  $clearedProductIds
     */
    private function attachImageToProduct(
        Product $product,
        SplFileInfo $file,
        string $sourcePath,
        string $targetDirectory,
        array &$clearedProductIds,
    ): bool {
        if (! $product->relationLoaded('productImages')) {
            $product->load('productImages');
        }

        if ($this->option('overwrite') && ! in_array($product->id, $clearedProductIds, true)) {
            if (! $this->option('dry-run')) {
                $this->clearProductImages($product);
            }

            $clearedProductIds[] = $product->id;
            $product->setRelation('productImages', collect());
        }

        $relativePath = $this->buildTargetRelativePath($product, $file, $sourcePath);

        $alreadyExists = $product->productImages
            ->contains(fn (ProductImage $image): bool => $image->path === $relativePath);

        if ($alreadyExists) {
            return false;
        }

        if ($this->option('dry-run')) {
            $nextImages = $product->productImages->push(new ProductImage([
                'path' => $relativePath,
                'sort_order' => ((int) $product->productImages->max('sort_order')) + 1,
                'is_cover' => $product->productImages->isEmpty(),
            ]));

            $product->setRelation('productImages', $nextImages->values());

            if (! filled($product->image_path)) {
                $product->image_path = $relativePath;
            }

            return true;
        }

        $sourceBinary = @file_get_contents($file->getPathname());

        if ($sourceBinary === false) {
            return false;
        }

        $normalizedBinary = $this->normalizeCatalogImageBinary($sourceBinary, (string) $file->getExtension());

        File::put($targetDirectory.'/'.basename($relativePath), $normalizedBinary);

        $isCover = $product->productImages->isEmpty();
        $newImage = $product->productImages()->create([
            'path' => $relativePath,
            'sort_order' => ((int) $product->productImages->max('sort_order')) + 1,
            'is_cover' => $isCover,
        ]);

        if ($isCover || ! filled($product->image_path)) {
            $product->forceFill(['image_path' => $relativePath])->save();
        }

        $product->setRelation('productImages', $product->productImages->push($newImage)->values());

        return true;
    }

    private function clearProductImages(Product $product): void
    {
        $product->loadMissing('productImages');

        foreach ($product->productImages as $image) {
            $path = public_path($image->path);

            if (File::exists($path)) {
                File::delete($path);
            }
        }

        $product->productImages()->delete();
        $product->forceFill(['image_path' => null])->save();
    }

    private function normalizeCatalogImageBinary(string $binary, string $extension): string
    {
        if (! function_exists('imagecreatefromstring')) {
            return $binary;
        }

        $extension = mb_strtolower(trim($extension));
        if (in_array($extension, ['svg', 'avif'], true)) {
            return $binary;
        }

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            return $binary;
        }

        try {
            $width = imagesx($image);
            $height = imagesy($image);

            if ($width <= 1 || $height <= 1) {
                return $binary;
            }

            $bounds = $this->detectNonTransparentBounds($image, $width, $height);
            if ($bounds === null) {
                return $binary;
            }

            $trimWidth = $bounds['right'] - $bounds['left'] + 1;
            $trimHeight = $bounds['bottom'] - $bounds['top'] + 1;

            if ($trimWidth <= 0 || $trimHeight <= 0) {
                return $binary;
            }

            if ($trimWidth === $width && $trimHeight === $height) {
                return $binary;
            }

            $trimmed = imagecreatetruecolor($trimWidth, $trimHeight);
            if ($trimmed === false) {
                return $binary;
            }

            try {
                imagealphablending($trimmed, false);
                $transparent = imagecolorallocatealpha($trimmed, 0, 0, 0, 127);
                imagefilledrectangle($trimmed, 0, 0, $trimWidth, $trimHeight, $transparent);
                imagesavealpha($trimmed, true);

                imagecopy(
                    $trimmed,
                    $image,
                    0,
                    0,
                    $bounds['left'],
                    $bounds['top'],
                    $trimWidth,
                    $trimHeight,
                );

                return $this->encodeCatalogImageBinary($trimmed, $extension) ?? $binary;
            } finally {
                imagedestroy($trimmed);
            }
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * @return array{left:int,top:int,right:int,bottom:int}|null
     */
    private function detectNonTransparentBounds($image, int $width, int $height): ?array
    {
        $left = $width;
        $top = $height;
        $right = -1;
        $bottom = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;

                if ($alpha === 127) {
                    continue;
                }

                if ($x < $left) {
                    $left = $x;
                }

                if ($x > $right) {
                    $right = $x;
                }

                if ($y < $top) {
                    $top = $y;
                }

                if ($y > $bottom) {
                    $bottom = $y;
                }
            }
        }

        if ($right < 0 || $bottom < 0) {
            return null;
        }

        return [
            'left' => $left,
            'top' => $top,
            'right' => $right,
            'bottom' => $bottom,
        ];
    }

    private function encodeCatalogImageBinary($image, string $extension): ?string
    {
        ob_start();

        try {
            $saved = match ($extension) {
                'png' => imagepng($image, null, 6),
                'gif' => imagegif($image),
                'webp' => function_exists('imagewebp') ? imagewebp($image, null, 85) : false,
                default => imagejpeg($image, null, 90),
            };

            $binary = ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            report($exception);

            return null;
        }

        if ($saved !== true || ! is_string($binary) || $binary === '') {
            return null;
        }

        return $binary;
    }

    private function buildTargetRelativePath(Product $product, SplFileInfo $file, string $sourcePath): string
    {
        $extension = mb_strtolower($file->getExtension());
        $baseName = $product->slug ?: Str::slug($product->publicTitle());
        $baseName = $baseName !== '' ? $baseName : 'product';
        $variantHash = substr(md5($this->relativePath($file, $sourcePath)), 0, 12);
        $fileName = sprintf('%d-%s-%s.%s', $product->id, $baseName, $variantHash, $extension);

        return 'catalog-media/figma/'.$fileName;
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            return base_path();
        }

        if (Str::startsWith($path, ['/', '\\']) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<string, Collection<int, Product>>
     */
    private function buildExactIndex(Collection $products): array
    {
        $index = [];

        foreach ($products as $product) {
            foreach ($this->productKeys($product) as $key) {
                $index[$key] ??= collect();

                if (! $index[$key]->contains(fn (Product $candidate): bool => $candidate->id === $product->id)) {
                    $index[$key]->push($product);
                }
            }
        }

        return $index;
    }

    /**
     * @param  array<string, Collection<int, Product>>  $exactIndex
     * @return Product|Collection<int, Product>|null
     */
    private function resolveProductMatch(SplFileInfo $file, string $sourcePath, Collection $products, array $exactIndex): Product|Collection|null
    {
        $normalizedCandidates = $this->candidateKeys($file, $sourcePath);
        $candidateTokens = $this->candidateTokens($file, $sourcePath);
        $exactAmbiguous = collect();

        foreach ($normalizedCandidates as $candidate) {
            $exactMatches = $exactIndex[$candidate] ?? collect();

            if ($exactMatches->count() === 1) {
                return $exactMatches->first();
            }

            if ($exactMatches->count() > 1) {
                $exactAmbiguous = $exactAmbiguous
                    ->merge($exactMatches)
                    ->unique('id')
                    ->values();
            }
        }

        if ($candidateTokens === []) {
            $preferredExact = $this->preferSingleProduct($exactAmbiguous);
            if ($preferredExact !== null) {
                return $preferredExact;
            }

            return $exactAmbiguous->isNotEmpty() ? $exactAmbiguous : null;
        }

        $strongCandidateTokens = $this->strongTokens($candidateTokens);
        $signatureCandidateTokens = $this->signatureTokens($candidateTokens);

        $scoredMatches = $products
            ->map(fn (Product $product): array => [
                'product' => $product,
                ...$this->scoreProductTokens($product, $candidateTokens, $strongCandidateTokens, $signatureCandidateTokens),
            ])
            ->filter(fn (array $row): bool => $row['score'] > 0)
            ->values();

        if ($scoredMatches->isEmpty()) {
            if ($exactAmbiguous->isNotEmpty()) {
                return $exactAmbiguous;
            }

            return null;
        }

        $maxSignatureMatched = (int) $scoredMatches->max('signature_matched');
        $scoredMatches = $scoredMatches
            ->filter(fn (array $row): bool => $row['signature_matched'] === $maxSignatureMatched)
            ->values();

        $maxStrongMatched = (int) $scoredMatches->max('strong_matched');
        $scoredMatches = $scoredMatches
            ->filter(fn (array $row): bool => $row['strong_matched'] === $maxStrongMatched)
            ->values();

        $maxScore = (int) $scoredMatches->max('score');

        if ($maxScore < 14) {
            $preferredExact = $this->preferSingleProduct($exactAmbiguous);
            if ($preferredExact !== null) {
                return $preferredExact;
            }

            return $exactAmbiguous->isNotEmpty() ? $exactAmbiguous : null;
        }

        $bestMatches = $scoredMatches
            ->filter(fn (array $row): bool => $row['score'] === $maxScore)
            ->pluck('product')
            ->unique('id')
            ->values();

        $preferredBest = $this->preferSingleProduct($bestMatches);
        if ($preferredBest !== null) {
            return $preferredBest;
        }

        if ($bestMatches->count() > 1) {
            return $bestMatches;
        }

        if ($exactAmbiguous->isNotEmpty()) {
            $preferredExact = $this->preferSingleProduct($exactAmbiguous);
            if ($preferredExact !== null) {
                return $preferredExact;
            }

            return $exactAmbiguous;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function productKeys(Product $product): array
    {
        $values = [
            $product->one_c_code,
            $product->vendor_code,
            $product->slug,
            $product->publicTitle(),
            $product->fullTitle(),
        ];

        return collect($values)
            ->map(fn ($value): string => $this->normalizeKey((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function stableSortKey(SplFileInfo $file, string $sourcePath): string
    {
        $relativePath = $this->relativePath($file, $sourcePath);
        $stem = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        $rank = preg_match('/^\d+$/', $stem) === 1 ? (int) $stem : 9999;

        return str_pad((string) $rank, 4, '0', STR_PAD_LEFT).'|'.$relativePath;
    }

    /**
     * @return list<string>
     */
    private function candidateKeys(SplFileInfo $file, string $sourcePath): array
    {
        $candidates = [];
        $relativePath = $this->relativePath($file, $sourcePath);
        $stem = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        $dir = trim((string) pathinfo($relativePath, PATHINFO_DIRNAME), './');
        $isGenericStem = preg_match('/^\d+(?:\s+\d+){0,2}$/u', $stem) === 1;

        if (! $isGenericStem) {
            $candidates[] = $stem;
            $candidates[] = pathinfo($relativePath, PATHINFO_FILENAME);
        }

        if ($dir !== '') {
            $dirSegments = array_values(array_filter(explode('/', $dir)));

            if ($dirSegments !== []) {
                $candidates[] = (string) end($dirSegments);
                $candidates[] = implode(' ', $dirSegments);
                $candidates[] = $dir;
            }
        }

        if ($isGenericStem && $dir !== '') {
            $candidates[] = $dir.' '.$stem;
        }

        return collect($candidates)
            ->map(fn (string $value): string => $this->normalizeKey($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function candidateTokens(SplFileInfo $file, string $sourcePath): array
    {
        $relativePath = $this->relativePath($file, $sourcePath);
        $stem = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        $dir = trim((string) pathinfo($relativePath, PATHINFO_DIRNAME), './');

        $phrases = [];
        $phrases[] = $relativePath;
        $phrases[] = $stem;
        if ($dir !== '') {
            $phrases[] = $dir;
        }

        return collect($phrases)
            ->flatMap(fn (string $value): array => $this->tokenize($value))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $candidateTokens
     * @param  list<string>  $strongCandidateTokens
     * @param  list<string>  $signatureCandidateTokens
     * @return array{score:int,strong_matched:int,signature_matched:int}
     */
    private function scoreProductTokens(Product $product, array $candidateTokens, array $strongCandidateTokens, array $signatureCandidateTokens): array
    {
        $productTokens = collect($this->productRawValues($product))
            ->flatMap(fn (string $value): array => $this->tokenize($value))
            ->unique()
            ->values()
            ->all();

        if ($productTokens === [] || $candidateTokens === []) {
            return ['score' => 0, 'strong_matched' => 0, 'signature_matched' => 0];
        }

        $shared = array_values(array_intersect($productTokens, $candidateTokens));
        if ($shared === []) {
            return ['score' => 0, 'strong_matched' => 0, 'signature_matched' => 0];
        }

        $signatureMatched = count(array_intersect($productTokens, $signatureCandidateTokens));
        if ($signatureCandidateTokens !== [] && $signatureMatched === 0) {
            return ['score' => 0, 'strong_matched' => 0, 'signature_matched' => 0];
        }

        $score = 0;
        $strongSignals = 0;
        $strongMatched = count(array_intersect($productTokens, $strongCandidateTokens));
        $strongMissing = max(0, count($strongCandidateTokens) - $strongMatched);

        foreach ($shared as $token) {
            $length = mb_strlen($token);
            $isNumeric = ctype_digit($token);
            $hasDigit = preg_match('/\d/u', $token) === 1;

            if ($isNumeric && $length >= 3) {
                $score += 16;
                $strongSignals++;
                continue;
            }

            if ($hasDigit && $length >= 3) {
                $score += 12;
                $strongSignals++;
                continue;
            }

            $score += min(10, $length);
            if ($length >= 6) {
                $strongSignals++;
            }
        }

        if ($strongSignals === 0 && count($shared) < 2) {
            return ['score' => 0, 'strong_matched' => 0, 'signature_matched' => $signatureMatched];
        }

        if (count($shared) >= 3) {
            $score += 6;
        }

        if ($signatureMatched > 0) {
            $score += $signatureMatched * 16;
        }

        if ($strongCandidateTokens !== []) {
            if ($strongMatched === 0) {
                return ['score' => 0, 'strong_matched' => 0, 'signature_matched' => $signatureMatched];
            }

            $score += ($strongMatched * 20) - ($strongMissing * 12);
        }

        return [
            'score' => max(0, $score),
            'strong_matched' => $strongMatched,
            'signature_matched' => $signatureMatched,
        ];
    }

    /**
     * @return list<string>
     */
    private function productRawValues(Product $product): array
    {
        return collect([
            (string) $product->one_c_id,
            (string) $product->one_c_code,
            (string) $product->vendor_code,
            (string) $product->slug,
            $product->publicTitle(),
            $product->fullTitle(),
        ])
            ->filter(fn (string $value): bool => trim($value) !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $value): array
    {
        $value = mb_strtolower(trim($value));
        if ($value === '') {
            return [];
        }

        $value = str_replace('ё', 'е', $value);
        $value = preg_replace('/\.[a-z0-9]+$/iu', '', $value) ?? $value;
        $value = preg_replace('/[^0-9a-zа-я]+/iu', ' ', $value) ?? $value;

        $parts = preg_split('/\s+/u', trim($value)) ?: [];
        $composed = [];

        for ($i = 0; $i < count($parts) - 1; $i++) {
            $left = $parts[$i];
            $right = $parts[$i + 1];

            if (
                preg_match('/^[a-zа-я]{3,}$/u', $left) === 1
                && preg_match('/^\d{1,3}[a-zа-я]?$/u', $right) === 1
            ) {
                $composed[] = $left.$right;
            }
        }

        return collect(array_merge($parts, $composed))
            ->map(fn (string $token): string => $this->normalizeToken($token))
            ->filter(fn (string $token): bool => ! $this->isNoiseToken($token))
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeToken(string $token): string
    {
        $aliases = [
            'черн' => 'черный',
            'чёрн' => 'черный',
            'черная' => 'черный',
            'черное' => 'черный',
            'бел' => 'белый',
            'белая' => 'белый',
            'белое' => 'белый',
            'мат' => 'матовый',
            'матовая' => 'матовый',
            'матовое' => 'матовый',
            'безокраса' => 'безокраса',
            'безокрас' => 'безокраса',
        ];

        return $aliases[$token] ?? $token;
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function strongTokens(array $tokens): array
    {
        return collect($tokens)
            ->filter(function (string $token): bool {
                if (preg_match('/^\d{3,}$/u', $token) === 1) {
                    return true;
                }

                if (preg_match('/\d/u', $token) === 1 && mb_strlen($token) >= 3) {
                    return true;
                }

                if (in_array($token, ['черный', 'белый', 'матовый', 'безокраса'], true)) {
                    return true;
                }

                return mb_strlen($token) >= 7;
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function signatureTokens(array $tokens): array
    {
        return collect($tokens)
            ->filter(function (string $token): bool {
                if (preg_match('/\d/u', $token) === 1) {
                    return mb_strlen($token) >= 2;
                }

                if (mb_strlen($token) < 5) {
                    return false;
                }

                return ! in_array($token, [
                    'профиль',
                    'гардина',
                    'блок',
                    'питания',
                    'алюминиевый',
                    'безокраса',
                    'черный',
                    'белый',
                    'матовый',
                    'leds',
                    'power',
                    'alteza',
                    'flexy',
                    'parsek',
                ], true);
            })
            ->unique()
            ->values()
            ->all();
    }

    private function isNoiseToken(string $token): bool
    {
        if ($token === '') {
            return true;
        }

        if (preg_match('/^\d{2,}$/u', $token) === 1) {
            return false;
        }

        if (preg_match('/^\d+[a-zа-я]$/u', $token) === 1) {
            return false;
        }

        if (mb_strlen($token) < 3) {
            return true;
        }

        return in_array($token, [
            'профиль',
            'алюминиевый',
            'ал',
            'блок',
            'питания',
            'leds',
            'power',
            'комплект',
            'уп',
            'м',
            'шт',
            'для',
            'под',
            'alteza',
            'flexy',
            'parsek',
        ], true);
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function preferSingleProduct(Collection $products): ?Product
    {
        if ($products->count() === 1) {
            return $products->first();
        }

        if ($products->isEmpty()) {
            return null;
        }

        $preferred = $products;

        foreach ([
            fn (Product $product): bool => filled($product->one_c_id),
            fn (Product $product): bool => filled($product->one_c_code),
            fn (Product $product): bool => filled($product->vendor_code),
        ] as $selector) {
            $subset = $preferred->filter($selector)->values();
            if ($subset->count() === 1) {
                return $subset->first();
            }

            if ($subset->count() > 1) {
                $preferred = $subset;
            }
        }

        $titles = $preferred
            ->map(fn (Product $product): string => $this->normalizeKey($product->publicTitle()))
            ->filter()
            ->unique()
            ->values();

        if ($titles->count() === 1) {
            return $preferred->sortByDesc('id')->first();
        }

        return null;
    }

    private function normalizeKey(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = str_replace('ё', 'е', $value);
        $value = preg_replace('/\.[a-z0-9]+$/iu', '', $value) ?? $value;
        $value = preg_replace('/[^0-9a-zа-я]+/iu', '', $value) ?? $value;

        return trim($value);
    }

    private function relativePath(SplFileInfo $file, string $sourcePath): string
    {
        return ltrim(Str::replace('\\', '/', Str::after($file->getPathname(), $sourcePath)), '/');
    }
}
