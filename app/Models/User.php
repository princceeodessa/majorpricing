<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'company',
    'contact_person',
    'contact_people',
    'phone',
    'telegram',
    'messengers',
    'delivery_address',
    'login',
    'email',
    'password',
    'price_profile_id',
    'is_active',
    'is_manager',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function priceProfile(): BelongsTo
    {
        return $this->belongsTo(PriceProfile::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function favoriteItems(): HasMany
    {
        return $this->hasMany(FavoriteItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest('placed_at');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function isManager(): bool
    {
        return (bool) $this->is_manager;
    }

    public function contactPeopleList(): array
    {
        return $this->normalizeStringList($this->contact_people, $this->contact_person);
    }

    public function messengersList(): array
    {
        return $this->normalizeStringList($this->messengers, $this->telegram);
    }

    public function primaryContactPerson(): ?string
    {
        return $this->contactPeopleList()[0] ?? ($this->contact_person ? trim((string) $this->contact_person) : null);
    }

    public function primaryMessenger(): ?string
    {
        return $this->messengersList()[0] ?? ($this->telegram ? trim((string) $this->telegram) : null);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'is_manager' => 'boolean',
            'contact_people' => 'array',
            'messengers' => 'array',
            'password' => 'hashed',
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
