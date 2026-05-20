<?php

namespace App\Support;

final class OrderStatuses
{
    public const NEW = 'new';
    public const ACCEPTED = 'accepted';
    public const ASSEMBLING = 'assembling';
    public const IN_TRANSIT = 'in_transit';
    public const COMPLETED = 'completed';
    public const CANCELED = 'canceled';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::NEW => 'Новый',
            self::ACCEPTED => 'Принят в работу',
            self::ASSEMBLING => 'На сборке',
            self::IN_TRANSIT => 'В пути',
            self::COMPLETED => 'Выполнен',
            self::CANCELED => 'Отменен',
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowed(): array
    {
        return array_keys(self::options());
    }

    /**
     * @return list<string>
     */
    public static function inProgress(): array
    {
        return [
            self::ACCEPTED,
            self::ASSEMBLING,
            self::IN_TRANSIT,
        ];
    }

    public static function label(?string $status): string
    {
        $normalized = self::normalize($status);

        return self::options()[$normalized] ?? ($status ?: 'Новый');
    }

    public static function normalize(?string $status): ?string
    {
        return match ($status) {
            'processing' => self::ACCEPTED,
            'payment_failed' => self::NEW,
            default => $status,
        };
    }
}
