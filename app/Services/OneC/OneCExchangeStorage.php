<?php

namespace App\Services\OneC;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class OneCExchangeStorage
{
    public function resetUploadState(string $sessionKey, string $type): void
    {
        Storage::disk('local')->deleteDirectory($this->typeDirectory($sessionKey, $type));
        Storage::disk('local')->delete($this->uploadStatePath($sessionKey, $type));
    }

    public function clearType(string $sessionKey, string $type): void
    {
        Storage::disk('local')->deleteDirectory($this->typeDirectory($sessionKey, $type));
        $this->resetUploadState($sessionKey, $type);

        if ($type === 'sale') {
            Cache::forget($this->exportedOrdersCacheKey($sessionKey));
        }
    }

    public function appendFile(string $sessionKey, string $type, string $filename, string $content): string
    {
        $sanitizedFilename = $this->sanitizeFilename($filename);
        $relativePath = $this->typeDirectory($sessionKey, $type).'/'.$sanitizedFilename;
        $absolutePath = Storage::disk('local')->path($relativePath);
        $state = $this->uploadState($sessionKey, $type);
        $isFirstChunk = ! in_array($sanitizedFilename, $state, true);

        File::ensureDirectoryExists(dirname($absolutePath));
        file_put_contents($absolutePath, $content, $isFirstChunk ? 0 : FILE_APPEND);

        if ($isFirstChunk) {
            $state[] = $sanitizedFilename;
            $this->storeUploadState($sessionKey, $type, $state);
        }

        return $relativePath;
    }

    /**
     * @return array<int, string>
     */
    public function xmlFiles(string $sessionKey, string $type): array
    {
        return collect(Storage::disk('local')->allFiles($this->typeDirectory($sessionKey, $type)))
            ->filter(fn (string $path): bool => str_ends_with(mb_strtolower($path), '.xml'))
            ->values()
            ->all();
    }

    public function fileContents(string $relativePath): ?string
    {
        if (! Storage::disk('local')->exists($relativePath)) {
            return null;
        }

        return Storage::disk('local')->get($relativePath);
    }

    public function resolveUploadedPath(string $sessionKey, string $type, string $filename): ?string
    {
        $relativePath = $this->typeDirectory($sessionKey, $type).'/'.$this->sanitizeFilename($filename);

        return Storage::disk('local')->exists($relativePath)
            ? Storage::disk('local')->path($relativePath)
            : null;
    }

    /**
     * @param  array<int, int>  $orderIds
     */
    public function rememberExportedOrderIds(string $sessionKey, array $orderIds): void
    {
        Cache::put($this->exportedOrdersCacheKey($sessionKey), $orderIds, now()->addHour());
    }

    /**
     * @return array<int, int>
     */
    public function pullExportedOrderIds(string $sessionKey): array
    {
        $value = Cache::pull($this->exportedOrdersCacheKey($sessionKey), []);

        return is_array($value) ? array_map('intval', $value) : [];
    }

    private function typeDirectory(string $sessionKey, string $type): string
    {
        return trim((string) config('integrations.one_c.upload_dir', 'one-c-exchange'), '/').'/'.$sessionKey.'/'.$type;
    }

    private function uploadStatePath(string $sessionKey, string $type): string
    {
        return $this->typeDirectory($sessionKey, $type).'/.upload-state.json';
    }

    /**
     * @return array<int, string>
     */
    private function uploadState(string $sessionKey, string $type): array
    {
        $path = $this->uploadStatePath($sessionKey, $type);

        if (! Storage::disk('local')->exists($path)) {
            return [];
        }

        $decoded = json_decode((string) Storage::disk('local')->get($path), true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $decoded)));
    }

    /**
     * @param  array<int, string>  $state
     */
    private function storeUploadState(string $sessionKey, string $type, array $state): void
    {
        $path = $this->uploadStatePath($sessionKey, $type);
        $absolutePath = Storage::disk('local')->path($path);

        File::ensureDirectoryExists(dirname($absolutePath));
        Storage::disk('local')->put($path, json_encode(array_values(array_unique($state)), JSON_UNESCAPED_UNICODE));
    }

    private function sanitizeFilename(string $filename): string
    {
        $normalized = str_replace('\\', '/', trim($filename));
        $segments = collect(explode('/', $normalized))
            ->filter(fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..')
            ->values();

        return $segments->isNotEmpty() ? $segments->implode('/') : 'exchange.xml';
    }

    private function exportedOrdersCacheKey(string $sessionKey): string
    {
        return 'one-c-exported-orders:'.$sessionKey;
    }
}
