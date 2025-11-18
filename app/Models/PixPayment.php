<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PixPayment extends Model
{
    protected $fillable = [
        'movement_id',
        'account_id',
        'pix_id',
        'transaction_id',
        'amount',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function movement()
    {
        return $this->belongsTo(Movement::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
