<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VerificationCode;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LZWController extends Controller
{
    protected $smsService;
    
    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }
    /**
     * 发送手机验证码
     * 接口: POST /api/auth/send-code
     */
    public function sendVerificationCode(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|regex:/^1[3-9]\d{9}$/',
            'type' => 'nullable|string|in:register',
        ]);

        $phone = $validated['phone'];
        $type = $validated['type'] ?? 'register';

        // 检查是否频繁发送（60秒内只能发一次）
        $lastCode = VerificationCode::where('phone', $phone)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subSeconds(60))
            ->first();

        if ($lastCode) {
            return response()->json([
                'code' => 429,
                'message' => '发送过于频繁，请60秒后再试',
                'data' => null
            ], 429);
        }

        // 生成6位随机验证码
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // 调用短信服务发送
        $result = $this->smsService->sendVerificationCode($phone, $code);
        
        if (!$result['success']) {
            return response()->json([
                'code' => 500,
                'message' => $result['message'],
                'data' => null
            ], 500);
        }

        // 保存验证码到数据库
        VerificationCode::create([
            'phone' => $phone,
            'code' => $code,
            'type' => $type,
            'expired_at' => now()->addMinutes(config('sms.code_expire', 5)),
            'is_used' => false,
        ]);

        // 开发环境返回验证码，生产环境不返回
        $data = ['phone' => $phone];
        if (config('app.env') !== 'production') {
            $data['code'] = $code;
        }

        return response()->json([
            'code' => 200,
            'message' => '验证码发送成功',
            'data' => $data
        ]);
    }

    /**
     * 校验验证码
     */
    private function verifyCode(string $phone, string $code, string $type = 'register'): bool
    {
        $verificationCode = VerificationCode::where('phone', $phone)
            ->where('code', $code)
            ->where('type', $type)
            ->where('is_used', false)
            ->where('expired_at', '>', now())
            ->latest()
            ->first();

        if (!$verificationCode) {
            return false;
        }

        // 标记为已使用
        $verificationCode->is_used = true;
        $verificationCode->save();

        return true;
    }

    /**
     * 用户注册（带验证码校验）
     * 接口: POST /api/auth/register
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'account' => 'required|string|unique:users',
            'name' => 'required|string',
            'password' => 'required|string|min:6',
            'phone' => 'required|string|regex:/^1[3-9]\d{9}$/',
            'code' => 'required|string|size:6',
            'email' => 'nullable|email',
            'role' => 'nullable|string|in:student,admin',
        ]);

        // 校验验证码
        if (!$this->verifyCode($validated['phone'], $validated['code'], 'register')) {
            return response()->json([
                'code' => 400,
                'message' => '验证码错误或已过期',
                'data' => null
            ]);
        }

        // 检查账号是否重复
        if (User::where('account', $validated['account'])->exists()) {
            return response()->json([
                'code' => 400,
                'message' => '账号已存在',
                'data' => null
            ]);
        }

        // 检查手机号是否已被注册
        if (User::where('phone', $validated['phone'])->exists()) {
            return response()->json([
                'code' => 400,
                'message' => '手机号已被注册',
                'data' => null
            ]);
        }

        $user = User::create([
            'account' => $validated['account'],
            'name' => $validated['name'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'],
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
     * 接口: /api/auth/login
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'account' => 'required|string',
            'password' => 'required|string',
            'remember' => 'nullable|boolean',
        ]);

        $user = User::where('account', $validated['account'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'code' => 400,
                'message' => '账号或密码错误',
                'data' => null
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'code' => 200,
            'message' => '登录成功',
            'data' => [
                'token' => $token,
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
        $user = $request->user();

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
        $request->user()->tokens()->delete();

        return response()->json([
            'code' => 200,
            'message' => '退出成功',
            'data' => null
        ]);
    }

    /**
     * 管理员获取所有用户列表
     * 接口: /api/admin/users
     */
    public function adminUsers(Request $request)
    {
        $user = $request->user();

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
        $user = $request->user();

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
