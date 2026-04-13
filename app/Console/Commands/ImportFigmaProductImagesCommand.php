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
            $product = $this->resolveProductMatch($file, $products, $exactIndex);

            if ($product === null) {
                $unmatched[] = $file->getFilename();
                continue;
            }

            if ($product instanceof Collection) {
                $ambiguous[] = [
                    'file' => $file->getFilename(),
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
    private function resolveProductMatch(SplFileInfo $file, Collection $products, array $exactIndex): Product|Collection|null
    {
        $stem = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        $normalizedStem = $this->normalizeKey($stem);

        if ($normalizedStem === '') {
            return null;
        }

        $exactMatches = $exactIndex[$normalizedStem] ?? collect();

        if ($exactMatches->count() === 1) {
            return $exactMatches->first();
        }

        if ($exactMatches->count() > 1) {
            return $exactMatches;
        }

        $partialMatches = $products
            ->filter(function (Product $product) use ($normalizedStem): bool {
                foreach ($this->productKeys($product) as $key) {
                    if ($key === '') {
                        continue;
                    }

                    if (
                        (mb_strlen($normalizedStem) >= 6 && str_contains($key, $normalizedStem))
                        || (mb_strlen($key) >= 6 && str_contains($normalizedStem, $key))
                    ) {
                        return true;
                    }
                }

                return false;
            })
            ->values();

        if ($partialMatches->count() === 1) {
            return $partialMatches->first();
        }

        if ($partialMatches->count() > 1) {
            return $partialMatches;
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

    private function normalizeKey(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = str_replace('ё', 'е', $value);
        $value = preg_replace('/\.[a-z0-9]+$/iu', '', $value) ?? $value;
        $value = preg_replace('/[^0-9a-zа-я]+/iu', '', $value) ?? $value;

        return trim($value);
    }
}
