<?php

use App\Http\Controllers\LXController;
use App\Http\Controllers\LZWController;
use App\Http\Controllers\WLJController;
use Illuminate\Support\Facades\Route;

// 公开接口（不需要token）
// 用户注册
Route::post('/register', [LZWcontroller::class, 'register']);
//用户登录
Route::post('/login', [LZWcontroller::class, 'login']);

// 需要登录认证的接口
Route::middleware('auth:api')->group(function () {
    //获取当前用户
    Route::get('/me', [LZWcontroller::class, 'me']);
    // 退出登录
    Route::post('/logout', [LZWcontroller::class, 'logout']); //

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

