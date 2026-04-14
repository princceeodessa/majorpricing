<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SplFileInfo;

#[Signature('catalog:import-figma-images {path : Path to directory with exported Figma product images} {--overwrite : Replace existing product images} {--dry-run : Show matches without writing files}')]
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
            ->select(['id', 'slug', 'title', 'name', 'one_c_code', 'vendor_code', 'image_path'])
            ->get();

        $exactIndex = $this->buildExactIndex($products);
        $targetDirectory = public_path('catalog-media/figma');

        if (! $this->option('dry-run')) {
            File::ensureDirectoryExists($targetDirectory);
        }

        $matched = 0;
        $updated = 0;
        $skipped = 0;
        $unmatched = [];
        $ambiguous = [];

        foreach ($files as $file) {
            $matched++;
            $product = $this->resolveProductMatch($file, $sourcePath, $products, $exactIndex);
            $relativePath = $this->relativePath($file, $sourcePath);

            if ($product === null) {
                $unmatched[] = $relativePath;
                continue;
            }

            if ($product instanceof Collection) {
                $ambiguous[] = [
                    'file' => $relativePath,
                    'products' => $product
                        ->take(4)
                        ->map(fn (Product $candidate): string => "#{$candidate->id} ".$candidate->publicTitle())
                        ->implode('; '),
                ];
                continue;
            }

            if (filled($product->image_path) && ! $this->option('overwrite')) {
                $skipped++;
                continue;
            }

            if ($this->option('dry-run')) {
                $updated++;
                continue;
            }

            $extension = mb_strtolower($file->getExtension());
            $baseName = $product->slug ?: Str::slug($product->publicTitle());
            $fileName = $product->id.'-'.($baseName !== '' ? $baseName : 'product').'.'.$extension;
            $relativePath = 'catalog-media/figma/'.$fileName;
            $targetPath = public_path($relativePath);

            File::copy($file->getPathname(), $targetPath);

            $product->forceFill([
                'image_path' => $relativePath,
            ])->save();

            $updated++;
        }

        $this->table(
            ['Показатель', 'Значение'],
            [
                ['Файлов найдено', $files->count()],
                ['Файлов обработано', $matched],
                ['Изображений привязано', $updated],
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

        $scoredMatches = $products
            ->map(function (Product $product) use ($normalizedCandidates): array {
                $score = 0;

                foreach ($this->productKeys($product) as $key) {
                    if ($key === '') {
                        continue;
                    }

                    foreach ($normalizedCandidates as $candidate) {
                        if ($candidate === '') {
                            continue;
                        }

                        $candidateLength = mb_strlen($candidate);
                        $keyLength = mb_strlen($key);

                        if ($candidateLength < 4 || $keyLength < 4) {
                            continue;
                        }

                        $contains = str_contains($key, $candidate) || str_contains($candidate, $key);

                        if (! $contains) {
                            continue;
                        }

                        $candidateScore = min($candidateLength, $keyLength);

                        if (preg_match('/\d/u', $candidate) === 1) {
                            $candidateScore += 8;
                        }

                        if ($candidateLength >= 12) {
                            $candidateScore += 4;
                        }

                        $score = max($score, $candidateScore);
                    }
                }

                return [
                    'product' => $product,
                    'score' => $score,
                ];
            })
            ->filter(fn (array $row): bool => $row['score'] > 0)
            ->values();

        if ($scoredMatches->isEmpty()) {
            if ($exactAmbiguous->isNotEmpty()) {
                return $exactAmbiguous;
            }

            return null;
        }

        $maxScore = (int) $scoredMatches->max('score');
        $bestMatches = $scoredMatches
            ->filter(fn (array $row): bool => $row['score'] === $maxScore)
            ->pluck('product')
            ->unique('id')
            ->values();

        if ($bestMatches->count() === 1) {
            return $bestMatches->first();
        }

        if ($bestMatches->count() > 1) {
            return $bestMatches;
        }

        if ($exactAmbiguous->isNotEmpty()) {
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
