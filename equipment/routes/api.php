<?php

use App\Http\Controllers\LXController;
use App\Http\Controllers\LZWController;
use App\Http\Controllers\WLJController;
use Illuminate\Support\Facades\Route;

// ======================
// 用户认证接口
// ======================
Route::post('/auth/register', [LZWController::class, 'register']);
Route::post('/auth/login', [LZWController::class, 'login']);

// 需要登录
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [LZWController::class, 'me']);
    Route::post('/auth/logout', [LZWController::class, 'logout']);

    // 管理员接口
    Route::get('/admin/users', [LZWController::class, 'adminUsers']);

    // =======================================
    // 王LJ负责的模块
    // =======================================
    
    // 设备大厅模块
    Route::get('/devices', [WLJController::class, 'getDevices']);
    Route::get('/devices/{id}', [WLJController::class, 'getDevice']);

    // 借用申请模块
    Route::post('/bookings', [WLJController::class, 'createBooking']);

    // 我的借用记录模块
    Route::get('/bookings/my', [WLJController::class, 'getMyBookings']);
    Route::patch('/bookings/{id}/return', [WLJController::class, 'returnBooking']);
});

// 需要 JWT 认证的路由组
Route::group(['middleware' => 'api'], function () {
    // 获取待审核申请列表
    Route::get('/admin/bookings/pending', [LXController::class, 'getPendingBookings']);

    // 审核借用申请（通过/拒绝）
    Route::patch('/admin/bookings/{id}/audit', [LXController::class, 'auditBooking']);

    // 新增设备
    Route::post('/admin/devices', [LXController::class, 'createDevice']);

    // 编辑设备
    Route::put('/admin/devices/{id}', [LXController::class, 'updateDevice']);

    // 下架设备（软删除）
    Route::delete('/admin/devices/{id}', [LXController::class, 'deleteDevice']);

});

