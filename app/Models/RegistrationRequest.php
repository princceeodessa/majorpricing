<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'company',
    'city',
    'price_profile_id',
    'contact_person',
    'contact_people',
    'phone',
    'telegram',
    'messengers',
    'delivery_address',
    'login',
    'email',
    'password',
    'status',
    'approved_by',
    'approved_user_id',
    'approved_at',
])]
#[Hidden(['password'])]
class RegistrationRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public function priceProfile(): BelongsTo
    {
        return $this->belongsTo(PriceProfile::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_user_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function contactPeopleList(): array
    {
        return $this->normalizeStringList($this->contact_people, $this->contact_person);
    }

    public function messengersList(): array
    {
        return $this->normalizeStringList($this->messengers, $this->telegram);
    }

    protected function casts(): array
    {
        return [
            'contact_people' => 'array',
            'messengers' => 'array',
            'approved_at' => 'datetime',
            'price_profile_id' => 'integer',
        ];
    }

    private function normalizeStringList(mixed $items, ?string $fallback = null): array
    {
        $normalized = collect(is_array($items) ? $items : [])
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();

        if ($normalized !== []) {
            return $normalized;
        }

        $fallback = trim((string) ($fallback ?? ''));

        return $fallback !== '' ? [$fallback] : [];
    }
}
