<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LZWController extends Controller
{
    /**
     * 用户注册
     * 接口: /api/register
     */
    public function register(Request $request)
    {
        // 数据验证
        $validated = $request->validate([
            'account' => 'required|string|max:50|unique:users',
            'name' => 'required|string|max:30',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|confirmed|min:6',
            'role' => 'nullable|string|in:student,admin',
        ]);

        // 创建用户
        $user = User::create([
            'account' => $validated['account'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'] ?? 'student',
        ]);

        // 生成 token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'code' => 200,
            'message' => '注册成功',
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ], 201);
    }

    /**
     * 用户登录
     * 接口: /api/login
     */
    public function login(Request $request)
    {
        // 验证账号密码
        $credentials = $request->validate([
            'account' => 'required|string',
            'password' => 'required|string',
        ]);

        // 查询用户
        $user = User::where('account', $credentials['account'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'account' => ['账号或密码不正确'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'code' => 200,
            'message' => '登录成功',
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ]);
    }

    /**
     * 获取当前登录用户信息
     * 接口: /api/me
     */
    public function me(Request $request)
    {
        $user = $request->user();

        // 判断是否管理员
        $isAdmin = $user->role === 'admin';

        return response()->json([
            'code' => 200,
            'message' => '获取用户信息成功',
            'data' => [
                'user' => $user,
                'is_admin' => $isAdmin, // 明确告诉前端是不是管理员
            ]
        ]);
    }

    /**
     * 退出登录
     * 接口: /api/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'code' => 200,
            'message' => '退出登录成功',
            'data' => null
        ]);
    }

    public function adminUsers(Request $request)
    {
        // 1. 获取当前登录用户
        $user = $request->user();

        // 2. 权限校验：必须是管理员
        if ($user->role !== 'admin') {
            return response()->json([
                'code' => 403,
                'message' => '权限不足，仅管理员可访问此接口',
                'data' => null
            ], 403);
        }

        // 3. 管理员权限通过，查询所有用户（排除密码等敏感字段）
        $users = \App\Models\User::select('id', 'account', 'name', 'email', 'role', 'created_at')
            ->get();

        // 4. 返回成功响应
        return response()->json([
            'code' => 200,
            'message' => '获取用户列表成功',
            'data' => $users
        ]);
    }
}
