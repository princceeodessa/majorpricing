<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'number',
        'status',
        'payment_status',
        'payment_method',
        'payment_reference',
        'paid_amount',
        'paid_at',
        'integration_reference',
        'one_c_document_id',
        'integration_status',
        'integration_last_error',
        'integration_synced_at',
        'one_c_exported_at',
        'one_c_updated_at',
        'payment_payload',
        'items_count',
        'subtotal_amount',
        'total_amount',
        'price_profile_name',
        'customer_name',
        'customer_company',
        'customer_email',
        'customer_phone',
        'customer_contact_person',
        'customer_telegram',
        'customer_delivery_address',
        'comment',
        'manager_comment',
        'placed_at',
    ];

    protected $casts = [
        'items_count' => 'integer',
        'subtotal_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'integration_synced_at' => 'datetime',
        'one_c_exported_at' => 'datetime',
        'one_c_updated_at' => 'datetime',
        'payment_payload' => 'array',
        'placed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }
}
