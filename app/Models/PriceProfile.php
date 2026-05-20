<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'column_index',
        'price_label',
        'description',
        'is_default',
    ];

    protected $casts = [
        'column_index' => 'integer',
        'is_default' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
