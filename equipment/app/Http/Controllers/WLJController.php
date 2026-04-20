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
            $keyword = $request->input('name');
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%' . $keyword . '%')
                  ->orWhere('description', 'like', '%' . $keyword . '%');
            });
        }

        // 支持按分类编码或分类名称筛选
        if ($request->has('category')) {
            $categoryValue = $request->input('category');
            // 先尝试按code匹配，如果没有再尝试按name匹配
            $categoryCode = \App\Models\Category::where('code', $categoryValue)
                ->orWhere('name', $categoryValue)
                ->value('code');
            $query->where('category', $categoryCode ?: $categoryValue);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // 分页
        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        $devices = $query->paginate($pageSize, ['*'], 'page', $page);

        // 获取分类信息并格式化数据
        $categories = \App\Models\Category::pluck('name', 'code')->toArray();
        
        $list = collect($devices->items())->map(function ($device) use ($categories) {
            return [
                'id' => $device->id,
                'name' => $device->name,
                'category' => $device->category,
                'category_name' => $categories[$device->category] ?? $device->category,
                'description' => $device->description,
                'total_qty' => $device->total_qty,
                'available_qty' => $device->available_qty,
                'status' => $device->status,
                'created_at' => $device->created_at,
                'updated_at' => $device->updated_at,
            ];
        });

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'total' => $devices->total(),
                'page' => $devices->currentPage(),
                'pageSize' => $devices->perPage(),
                'list' => $list
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

        // 获取分类信息
        $category = \App\Models\Category::where('code', $device->category)->first();
        
        // 实时计算可用库存
        // 1. 已借出 = pending + approved + returning
        $borrowedCount = Booking::where('device_id', $device->id)
            ->whereIn('status', ['approved', 'pending', 'returning'])
            ->count();
        
        // 2. 损坏/不可用 = 被拒绝且 reason_type = device_unavailable
        $brokenCount = Booking::where('device_id', $device->id)
            ->where('status', 'rejected')
            ->where('reason_type', 'device_unavailable')
            ->count();
        
        // 3. 可用数量 = 总数量 - 已借出 - 损坏/不可用
        $realAvailableQty = $device->total_qty - $borrowedCount - $brokenCount;
        
        // 获取相关设备（同分类）
        $relatedDevices = Device::where('category', $device->category)
            ->where('id', '!=', $device->id)
            ->where('status', 'available')
            ->limit(5)
            ->get(['id', 'name', 'total_qty'])
            ->map(function ($relatedDevice) {
                // 实时计算相关设备的可用库存（占用库存的状态：pending + approved + returning）
                $relatedBorrowedCount = Booking::where('device_id', $relatedDevice->id)
                    ->whereIn('status', ['approved', 'pending', 'returning'])
                    ->count();
                $relatedBrokenCount = Booking::where('device_id', $relatedDevice->id)
                    ->where('status', 'rejected')
                    ->where('reason_type', 'device_unavailable')
                    ->count();
                $relatedDevice->available_qty = $relatedDevice->total_qty - $relatedBorrowedCount - $relatedBrokenCount;
                return $relatedDevice;
            });

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'id' => $device->id,
                'name' => $device->name,
                'category' => $device->category,
                'category_info' => $category ? [
                    'id' => $category->id,
                    'name' => $category->name,
                    'code' => $category->code,
                    'description' => $category->description,
                ] : null,
                'description' => $device->description,
                'total_qty' => $device->total_qty,
                'available_qty' => $realAvailableQty,  // 实时计算的可用数量
                'status' => $device->status,
                'related_devices' => $relatedDevices,
                'created_at' => $device->created_at,
                'updated_at' => $device->updated_at,
            ]
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

        // 实时计算可用库存（占用库存的状态：pending + approved + returning）
        $borrowedCount = Booking::where('device_id', $device->id)
            ->whereIn('status', ['approved', 'pending', 'returning'])
            ->count();
        $brokenCount = Booking::where('device_id', $device->id)
            ->where('status', 'rejected')
            ->where('reason_type', 'device_unavailable')
            ->count();
        $availableQty = $device->total_qty - $borrowedCount - $brokenCount;

        // 检查设备是否有可用库存
        if ($availableQty <= 0) {
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
            // 获取分类详细信息
            $category = \App\Models\Category::where('code', $booking->device->category)->first();

            return [
                'id' => $booking->id,
                'device_name' => $booking->device->name,
                'borrow_start' => $booking->borrow_start,
                'borrow_end' => $booking->borrow_end,
                'purpose' => $booking->purpose,
                'status' => $booking->status,
                'created_at' => $booking->created_at,
                'device' => [
                    'id' => $booking->device->id,
                    'name' => $booking->device->name,
                    'category_code' => $booking->device->category,
                    'category' => $category ? [
                        'id' => $category->id,
                        'name' => $category->name,
                        'code' => $category->code,
                        'description' => $category->description,
                    ] : null,
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

    // 申请归还设备（用户发起，需要管理员审核）
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

        // 更新状态为申请归还（待管理员审核）
        $booking->update(['status' => 'returning']);

        return response()->json([
            'code' => 200,
            'message' => '归还申请已提交，等待管理员审核',
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
    // 编辑设备信息
    public function updateDevice(Request $request, $id)
    {
        $device = Device::find($id);

        if (!$device) {
            return response()->json([
                'code' => 404,
                'message' => '设备不存在',
                'data' => null
            ]);
        }

        $user->forceDelete();

        return response()->json([
            'code' => 200,
            'message' => '账号已注销',
            'data' => null
        $request->validate([
            'name' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'total_qty' => 'nullable|integer|min:1',
            'available_qty' => 'nullable|integer|min:0',
            'status' => 'nullable|in:available,maintenance',
        ]);

        // 如果更新了分类，检查分类是否存在
        if ($request->has('category')) {
            $categoryCode = $request->input('category');
            $category = \App\Models\Category::where('code', $categoryCode)->first();
            if (!$category) {
                return response()->json([
                    'code' => 400,
                    'message' => '设备分类不存在，请先创建分类或使用现有分类',
                    'data' => null
                ], 400);
            }
        }

        // 更新设备（只更新传了的字段）
        if ($request->has('name')) {
            $device->name = $request->input('name');
        }
        if ($request->has('category')) {
            $device->category = $request->input('category');
        }
        if ($request->has('description')) {
            $device->description = $request->input('description');
        }
        if ($request->has('total_qty')) {
            $device->total_qty = $request->input('total_qty');
        }
        if ($request->has('available_qty')) {
            $device->available_qty = $request->input('available_qty');
        }
        if ($request->has('status')) {
            $device->status = $request->input('status');
        }

        $device->save();

        return response()->json([
            'code' => 200,
            'message' => '设备更新成功',
            'data' => $device
        ]);
    }
}
