<?php

namespace App\Console\Commands;

use App\Services\CatalogImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('catalog:import {path? : Path to the XLSX catalog file}')]
#[Description('Imports the product catalog and personalized price columns from an Excel workbook')]
class ImportPriceCatalog extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CatalogImportService $catalogImportService): int
    {
        $inputPath = $this->argument('path');
        $path = storage_path('app/price-import.xlsx');

        if ($inputPath) {
            $path = Str::startsWith($inputPath, ['/', '\\']) || preg_match('/^[A-Za-z]:\\\\/', $inputPath)
                ? $inputPath
                : base_path($inputPath);
        }

        if (! is_file($path)) {
            $this->components->error("Файл каталога не найден: {$path}");

            return self::FAILURE;
        }

        $stats = $catalogImportService->import($path, $this);

        $this->newLine();
        $this->table(
            ['Показатель', 'Значение'],
            [
                ['Листов', $stats['sheets']],
                ['Подразделов', $stats['sections']],
                ['Товаров', $stats['products']],
                ['Ценовых записей', $stats['prices']],
            ],
        );

        $this->components->info('Импорт каталога завершен.');

        return self::SUCCESS;
    }
}
