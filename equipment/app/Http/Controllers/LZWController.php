<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class LZWController extends Controller
{
    /**
     * 用户注册
     * 接口: POST /api/auth/register
     */
    public function register(Request $request)
    {
        // 确保返回 JSON
        $request->headers->set('Accept', 'application/json');
        
        try {
            $validated = $request->validate([
                'account' => 'required|string|unique:users',
                'name' => 'required|string',
                'password' => 'required|string|min:6',
                'email' => 'nullable|email',
                'role' => 'nullable|string|in:student,admin',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'message' => '验证失败',
                'data' => $e->errors()
            ], 422);
        }

        // 检查账号是否重复
        if (User::where('account', $validated['account'])->exists()) {
            return response()->json([
                'code' => 400,
                'message' => '账号已存在',
                'data' => null
            ]);
        }

        $user = User::create([
            'account' => $validated['account'],
            'name' => $validated['name'],
            'password' => $validated['password'],
            'email' => $validated['email'] ?? null,
            'role' => $validated['role'] ?? 'student',
        ]);

        return response()->json([
            'code' => 200,
            'message' => '注册成功',
            'data' => [
                'id' => $user->id,
                'account' => $user->account,
                'name' => $user->name,
                'role' => $user->role,
            ]
        ]);
    }

    /**
     * 用户登录
     * 接口: POST /api/auth/login
     * 说明: 登录不需要token，登录成功后返回token
     */
    public function login(Request $request)
    {
        // 验证请求参数
        $validated = $request->validate([
            'account' => 'required|string',
            'password' => 'required|string',
        ]);

        // 准备认证凭据
        $credentials = [
            'account' => $validated['account'],
            'password' => $validated['password'],
        ];

        // 尝试使用JWT认证
        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'code' => 401,
                'message' => '账号或密码错误',
                'data' => null
            ], 401);
        }

        // 获取当前认证用户
        $user = Auth::user();

        // 返回登录成功信息和token
        return response()->json([
            'code' => 200,
            'message' => '登录成功',
            'data' => [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => [
                    'id' => $user->id,
                    'account' => $user->account,
                    'name' => $user->name,
                    'role' => $user->role,
                ]
            ]
        ]);
    }

    /**
     * 获取当前用户信息
     * 接口: /api/auth/me
     */
    public function me(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token无效',
                'data' => null
            ], 401);
        }

        return response()->json([
            'code' => 200,
            'message' => '获取成功',
            'data' => [
                'id' => $user->id,
                'account' => $user->account,
                'name' => $user->name,
                'role' => $user->role,
                'email' => $user->email,
            ]
        ]);
    }

    /**
     * 退出登录
     * 接口: /api/auth/logout
     */
    public function logout(Request $request)
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'code' => 200,
                'message' => '退出成功',
                'data' => null
            ]);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'code' => 500,
                'message' => '退出失败：' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * 管理员获取所有用户列表
     * 接口: /api/admin/users
     */
    public function adminUsers(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token无效',
                'data' => null
            ], 401);
        }

        if ($user->role !== 'admin') {
            return response()->json([
                'code' => 403,
                'message' => '权限不足，仅管理员可访问',
                'data' => null
            ], 403);
        }

        $users = User::select('id', 'account', 'name', 'email', 'role', 'created_at')->get();

        return response()->json([
            'code' => 200,
            'message' => '获取用户列表成功',
            'data' => $users
        ]);
    }
    /**
     * 忘记密码 / 重置密码
     * 接口: /api/auth/forget-password
     */
    public function forgetPassword(Request $request)
    {
        // 1. 验证参数
        $validated = $request->validate([
            'account' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // 2. 查询用户是否存在
        $user = User::where('account', $validated['account'])->first();

        if (!$user) {
            return response()->json([
                'code' => 400,
                'message' => '账号不存在',
                'data' => null
            ]);
        }

        // 3. 校验邮箱是否一致
        if ($user->email !== $validated['email']) {
            return response()->json([
                'code' => 400,
                'message' => '邮箱与账号不匹配',
                'data' => null
            ]);
        }

        // 4. 重置密码
        $user->update([
            'password' => Hash::make($validated['password'])
        ]);

        // 5. 返回成功
        return response()->json([
            'code' => 200,
            'message' => '密码重置成功',
            'data' => null
        ]);
    }
    /**
     * 修改个人信息
     * 接口: /api/auth/profile
     */
    public function updateProfile(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录或token无效',
                'data' => null
            ], 401);
        }

        $validated = $request->validate([
            'name' => 'nullable|string',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
        ]);

        // 如果传了name就更新
        if (!empty($validated['name'])) {
            $user->name = $validated['name'];
        }

        // 如果传了email就更新
        if (!empty($validated['email'])) {
            $user->email = $validated['email'];
        }

        // 如果传了密码就更新
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return response()->json([
            'code' => 200,
            'message' => '修改成功',
            'data' => [
                'id' => $user->id,
                'account' => $user->account,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ]);
    }
}
