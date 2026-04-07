<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OneCPriceType extends Model
{
    use HasFactory;

    protected $fillable = [
        'one_c_id',
        'name',
        'column_index',
    ];

    protected $casts = [
        'column_index' => 'integer',
    ];
}
