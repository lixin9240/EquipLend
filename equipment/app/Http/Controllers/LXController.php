<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class LXController extends \Illuminate\Routing\Controller
{
    /**
     * 获取当前登录用户（JWT）
     */
    protected function getCurrentUser()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return null;
            }
            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 检查是否是管理员
     */
    protected function isAdmin(): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user->role === 'admin';
    }

    /**
     * 获取待审核申请列表（管理员功能）
     * GET /api/admin/bookings/pending
     */
    public function getPendingBookings(Request $request): JsonResponse
    {
        // JWT 认证检查
        $user = $this->getCurrentUser();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token已过期'
            ], 401);
        }

        // 检查是否是管理员
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限访问，只有管理员可以查看待审核列表'
            ], 403);
        }

        // 获取分页参数
        $page = $request->input('page', 1);
        $pageSize = $request->input('pageSize', 10);

        // 获取待审核列表，关联用户和设备信息
        $query = Booking::with(['user', 'device'])
            ->where('status', Booking::STATUS_PENDING)
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $bookings = $query->forPage($page, $pageSize)->get();

        // 格式化返回数据
        $list = $bookings->map(function ($booking) {
            return [
                'id' => $booking->id,
                'user_name' => $booking->user->name ?? '',
                'device_name' => $booking->device->name ?? '',
                'borrow_start' => $booking->borrow_start->format('Y-m-d'),
                'borrow_end' => $booking->borrow_end->format('Y-m-d'),
                'status' => $booking->status,
                'created_at' => $booking->created_at->format('Y-m-d H:i:s'),
                'user' => [
                    'id' => $booking->user->id ?? null,
                    'account' => $booking->user->account ?? '',
                    'name' => $booking->user->name ?? ''
                ],
                'device' => [
                    'id' => $booking->device->id ?? null,
                    'name' => $booking->device->name ?? '',
                    'available_qty' => $booking->device->available_qty ?? 0
                ]
            ];
        });

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'total' => $total,
                'page' => (int) $page,
                'pageSize' => (int) $pageSize,
                'list' => $list
            ]
        ]);
    }

    
    /**
     * 审核借用申请（管理员功能）
     * PATCH /api/admin/bookings/{id}/audit
     */
    public function auditBooking(Request $request, $id): JsonResponse
    {
        // JWT 认证检查
        $user = $this->getCurrentUser();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token已过期'
            ], 401);
        }

        // 检查是否是管理员
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限访问，只有管理员可以审核申请'
            ], 403);
        }

        // 验证参数
        $request->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'required_if:action,reject|string|max:255',
        ]);

        // 查找申请记录
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json([
                'code' => 404,
                'message' => '申请记录不存在'
            ], 404);
        }

        // 检查是否已经是待审核状态
        if ($booking->status !== Booking::STATUS_PENDING) {
            return response()->json([
                'code' => 400,
                'message' => '该申请已处理，无法重复审核'
            ], 400);
        }

        $action = $request->input('action');

        if ($action === 'approve') {
            // 批准申请
            $booking->status = Booking::STATUS_APPROVED;
            $booking->save();

            return response()->json([
                'code' => 200,
                'message' => '申请已通过',
                'data' => $booking
            ]);
        } else {
            // 拒绝申请
            $booking->status = Booking::STATUS_REJECTED;
            $booking->reason = $request->input('reason');
            $booking->save();

            return response()->json([
                'code' => 200,
                'message' => '申请已拒绝',
                'data' => $booking
            ]);
        }
    }

    /**
     * 新增设备（管理员功能）
     * POST /api/admin/devices
     */
    public function createDevice(Request $request): JsonResponse
    {
        // JWT 认证检查
        $user = $this->getCurrentUser();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token已过期'
            ], 401);
        }

        // 检查是否是管理员
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限访问，只有管理员可以新增设备'
            ], 403);
        }

        // 验证参数
        $request->validate([
            'name' => 'required|string|max:100',
            'category' => 'required|string|max:50',
            'description' => 'nullable|string',
            'total_qty' => 'required|integer|min:1',
            'available_qty' => 'required|integer|min:0',
            'status' => 'required|in:available,maintenance',
        ]);

        // 检查是否已存在相同名称和分类的设备
        $existingDevice = Device::where('name', $request->input('name'))
            ->where('category', $request->input('category'))
            ->first();

        if ($existingDevice) {
            return response()->json([
                'code' => 400,
                'message' => '该设备已存在，请勿重复添加',
                'data' => null
            ], 400);
        }

        // 创建设备
        $device = Device::create([
            'name' => $request->input('name'),
            'category' => $request->input('category'),
            'description' => $request->input('description'),
            'total_qty' => $request->input('total_qty'),
            'available_qty' => $request->input('available_qty'),
            'status' => $request->input('status'),
        ]);

        return response()->json([
            'code' => 200,
            'message' => '设备新增成功',
            'data' => $device
        ]);
    }

    /**
     * 编辑设备（管理员功能）
     * PUT /api/admin/devices/{id}
     */
    public function updateDevice(Request $request, $id): JsonResponse
    {
        // JWT 认证检查
        $user = $this->getCurrentUser();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token已过期'
            ], 401);
        }

        // 检查是否是管理员
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限访问，只有管理员可以编辑设备'
            ], 403);
        }

        // 查找设备
        $device = Device::find($id);
        if (!$device) {
            return response()->json([
                'code' => 404,
                'message' => '设备不存在'
            ], 404);
        }

        // 验证参数（均为可选）
        $request->validate([
            'name' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'total_qty' => 'nullable|integer|min:1',
            'available_qty' => 'nullable|integer|min:0',
            'status' => 'nullable|in:available,maintenance',
        ]);

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

    /**
     * 下架设备（软删除，管理员功能）
     * DELETE /api/admin/devices/{id}
     */
    public function deleteDevice(Request $request, $id): JsonResponse
    {
        // JWT 认证检查
        $user = $this->getCurrentUser();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token已过期'
            ], 401);
        }

        // 检查是否是管理员
        if (!$this->isAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权限访问，只有管理员可以下架设备'
            ], 403);
        }

        // 查找设备
        $device = Device::find($id);
        if (!$device) {
            return response()->json([
                'code' => 404,
                'message' => '设备不存在'
            ], 404);
        }

        // 软删除设备
        $device->delete();

        return response()->json([
            'code' => 200,
            'message' => '设备已下架'
        ]);
    }
}
