<?php

use App\Http\Controllers\LXController;
use App\Http\Controllers\LZWController;
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

