<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasFactory, SoftDeletes;

    protected $table = 'users';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'account',
        'name',
        'password',
        'role',
        'email',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // 角色常量
    const ROLE_STUDENT = 'student';
    const ROLE_ADMIN = 'admin';

    /**
     * 用户借用记录
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * 作用域：学生
     */
    public function scopeStudent($query)
    {
        return $query->where('role', self::ROLE_STUDENT);
    }

    /**
     * 作用域：管理员
     */
    public function scopeAdmin($query)
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    /**
     * 是否管理员
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * 是否学生
     */
    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }
}