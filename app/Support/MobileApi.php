<?php

namespace App\Support;

final class MobileApi
{
    public static function money(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    public static function quantity(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $quantity = (float) $value;

        return round($quantity, 3);
    }

    public static function assetUrl(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        return asset($path);
    }
}
