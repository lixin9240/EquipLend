<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerificationCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'code',
        'type',
        'expired_at',
        'is_used',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    /**
     * 检查验证码是否有效
     */
    public function isValid(): bool
    {
        return !$this->is_used && $this->expired_at->isFuture();
    }
}