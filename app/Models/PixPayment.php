<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PixPayment extends Model
{
    protected $fillable = ['pix_id', 'user_id', 'amount', 'status', 'meta'];

    protected $casts = [
        'meta' => 'array',
    ];
}