<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class WLJController extends \Illuminate\Routing\Controller
{
    // 获取设备列表（分页+筛选）
    public function getDevices(Request $request)
    {
        $query = Device::query();

        // 筛选条件
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%')
                  ->orWhere('description', 'like', '%' . $request->name . '%');
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // 分页
        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        $devices = $query->paginate($pageSize, ['*'], 'page', $page);

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'total' => $devices->total(),
                'page' => $devices->currentPage(),
                'pageSize' => $devices->perPage(),
                'list' => $devices->items()
            ]
        ]);
    }

    // 获取设备详情
    public function getDevice($id)
    {
        $device = Device::find($id);

        if (!$device) {
            return response()->json([
                'code' => 404,
                'message' => '设备不存在',
                'data' => null
            ]);
        }

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => $device
        ]);
    }

    // 发起借用申请
    public function createBooking(Request $request)
    {
        $request->validate([
            'device_id' => 'required|exists:devices,id',
            'borrow_start' => 'required|date',
            'borrow_end' => 'required|date|after_or_equal:borrow_start',
            'purpose' => 'nullable|string'
        ]);

        $device = Device::find($request->device_id);

        // 检查设备是否有可用库存
        if ($device->available_qty <= 0) {
            return response()->json([
                'code' => 400,
                'message' => '该设备当前无可用库存，请选择其他时间或设备',
                'data' => null
            ]);
        }

        // 创建借用申请
        $booking = Booking::create([
            'user_id' => Auth::id(),
            'device_id' => $request->device_id,
            'borrow_start' => $request->borrow_start,
            'borrow_end' => $request->borrow_end,
            'purpose' => $request->purpose,
            'status' => 'pending'
        ]);

        // 减少设备可用数量
        $device->available_qty -= 1;
        $device->save();

        return response()->json([
            'code' => 200,
            'message' => '申请已提交，等待审核',
            'data' => $booking
        ]);
    }

    // 获取个人借用记录
    public function getMyBookings(Request $request)
    {
            $query = Booking::where('user_id', Auth::id())->with('device:id,name,category');

        // 状态筛选
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // 分页
        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        $bookings = $query->paginate($pageSize, ['*'], 'page', $page);

        // 格式化数据
        $list = $bookings->map(function ($booking) {
            return [
                'id' => $booking->id,
                'device_name' => $booking->device->name,
                'borrow_start' => $booking->borrow_start,
                'borrow_end' => $booking->borrow_end,
                'status' => $booking->status,
                'created_at' => $booking->created_at,
                'device' => [
                    'id' => $booking->device->id,
                    'name' => $booking->device->name,
                    'category' => $booking->device->category
                ]
            ];
        });

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'total' => $bookings->total(),
                'page' => $bookings->currentPage(),
                'pageSize' => $bookings->perPage(),
                'list' => $list
            ]
        ]);
    }

    // 申请归还设备
    public function returnBooking($id)
    {
        $booking = Booking::where('id', $id)->where('user_id', Auth::id())->first();

        if (!$booking) {
            return response()->json([
                'code' => 404,
                'message' => '借用记录不存在',
                'data' => null
            ]);
        }

        if ($booking->status != 'approved') {
            return response()->json([
                'code' => 400,
                'message' => '仅已通过的申请可发起归还',
                'data' => null
            ]);
        }

        // 更新状态为已归还
        $booking->update(['status' => 'returned']);

        // 增加设备可用数量
        $device = $booking->device;
        $device->available_qty += 1;
        $device->save();

        return response()->json([
            'code' => 200,
            'message' => '归还成功',
            'data' => [
                'id' => $booking->id,
                'status' => $booking->status,
                'updated_at' => $booking->updated_at
            ]
        ]);
    }

    // 注销账号（硬删除）
    public function deleteAccount(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录',
                'data' => null
            ]);
        }

        $user->forceDelete();

        return response()->json([
            'code' => 200,
            'message' => '账号已注销',
            'data' => null
        ]);
    }
}
