<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'devices';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'category',
        'description',
        'total_qty',
        'available_qty',
        'status',
    ];

    protected $casts = [
        'total_qty' => 'integer',
        'available_qty' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // 状态常量
    const STATUS_AVAILABLE = 'available';
    const STATUS_MAINTENANCE = 'maintenance';

    /**
     * 设备借用记录
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * 作用域：可借用
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    /**
     * 作用域：维护中
     */
    public function scopeMaintenance($query)
    {
        return $query->where('status', self::STATUS_MAINTENANCE);
    }

    /**
     * 是否有库存
     */
    public function inStock(): bool
    {
        return $this->available_qty > 0;
    }
}