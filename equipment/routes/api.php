<?php

use App\Http\Controllers\LXController;
use App\Http\Controllers\LZWController;
use App\Http\Controllers\WLJController;
use Illuminate\Support\Facades\Route;


    // 公开接口 
    Route::post('/auth/register', [LZWController::class, 'register']);//注册
    Route::post('/auth/login', [LZWController::class, 'login']);//登录（需要邮箱验证码）
    Route::post('/auth/forget-password', [LZWController::class, 'forgetPassword']);//忘记密码
    Route::post('/auth/send-email-code', [LZWController::class, 'sendEmailCode']);//发送邮箱验证码

   
    Route::get('/categories', [LXController::class, 'getCategories']);           // 获取分类列表
    Route::get('/categories/all', [LXController::class, 'getAllCategories']);         // 获取所有启用的分类（下拉选择）
    Route::get('/categories/statistics', [LXController::class, 'getCategoryStatistics']); // 分类统计
    Route::get('/categories/{id}', [LXController::class, 'getCategory']);       // 获取分类详情

    // 需要认证的接口
    Route::group(['middleware' => 'jwt.auth'], function () {
    Route::get('/auth/me', [LZWController::class, 'me']);//获取当前用户信息
    Route::post('/auth/logout', [LZWController::class, 'logout']);//退出登录
    Route::put('/auth/profile', [LZWController::class, 'updateProfile']);//更新用户信息
    // 管理员接口
    Route::get('/admin/users', [LZWController::class, 'adminUsers']);//获取所有用户列表

    // =======================================
    // 设备分类模块（管理员接口）
    // =======================================
    Route::post('/admin/categories', [LXController::class, 'createCategory']);      // 创建分类
    Route::put('/admin/categories/{id}', [LXController::class, 'updateCategory']); // 更新分类
    Route::delete('/admin/categories/{id}', [LXController::class, 'deleteCategory']); // 删除分类


    // 设备大厅模块
    Route::get('/devices', [WLJController::class, 'getDevices']);//获取设备列表
    Route::get('/devices/{id}', [WLJController::class, 'getDevice']);//获取设备详情
    Route::put('/devices/{id}', [WLJController::class, 'updateDevice']);//编辑设备信息

    // 借用申请模块
    Route::post('/bookings', [WLJController::class, 'createBooking']);

    // 我的借用记录模块
    Route::get('/bookings/my', [WLJController::class, 'getMyBookings']);//获取我的借用记录
    Route::patch('/bookings/{id}/return', [WLJController::class, 'returnBooking']);//归还设备
    Route::get('/admin/bookings/pending', [LXController::class, 'getPendingBookings']);//获取待审核申请列表
    Route::patch('/admin/bookings/{id}/audit', [LXController::class, 'auditBooking']);//审核借用申请


    Route::post('/admin/devices', [LXController::class, 'createDevice']);//创建设备
    Route::put('/admin/devices/{id}', [LXController::class, 'updateDevice']);//更新设备信息
    Route::delete('/admin/devices/{id}', [LXController::class, 'deleteDevice']);//下架设备（软删除）

});

