<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MobileAccessToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token_hash',
        'device_name',
        'platform',
        'app_version',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array{0: self, 1: string}
     */
    public static function issueForUser(User $user, array $metadata = []): array
    {
        $plainToken = Str::random(80);

        $token = self::query()->create([
            'user_id' => $user->id,
            'token_hash' => self::hashPlainToken($plainToken),
            'device_name' => $metadata['device_name'] ?? null,
            'platform' => $metadata['platform'] ?? null,
            'app_version' => $metadata['app_version'] ?? null,
            'expires_at' => now()->addDays(90),
        ]);

        return [$token, $plainToken];
    }

    public static function hashPlainToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function isUsable(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }
}
