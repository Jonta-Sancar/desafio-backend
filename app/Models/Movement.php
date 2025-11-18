<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Movement extends Model
{
    use HasFactory;

    public const TYPE_PIX = 'PIX';
    public const TYPE_WITHDRAW = 'WITHDRAW';

    public const STATUS_CREATED = 'CREATED';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_FAILED = 'FAILED';

    protected $fillable = [
        'account_id',
        'type',
        'status',
        'amount',
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function pixPayment()
    {
        return $this->hasOne(PixPayment::class);
    }

    public function withdrawal()
    {
        return $this->hasOne(Withdrawal::class);
    }
}
