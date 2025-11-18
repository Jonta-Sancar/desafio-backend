<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    protected $fillable = ['withdraw_id', 'user_id', 'amount', 'status', 'meta'];

    protected $casts = [
        'meta' => 'array',
    ];
}
