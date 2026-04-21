<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EmailVerificationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class LZWController extends Controller
{
    protected EmailVerificationService $emailVerificationService;

    public function __construct(EmailVerificationService $emailVerificationService)
    {
        $this->emailVerificationService = $emailVerificationService;
    }
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
                'account' => 'required|string|min:4|max:20|unique:users',
                'name' => 'required|string|min:2|max:20',
                'password' => 'required|string|min:6|regex:/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]+$/',
                'password_confirmation' => 'required|string|same:password',
                'email' => 'required|email|max:100|regex:/^[a-zA-Z0-9._%+-]+@qq\.com$/i',
                'email_code' => 'required|string|size:6',

                // ###########################
                // 第1处修改：直接删掉 role 验证！不让前端传！
                // ###########################
            ], [
                'account.min' => '账号至少4个字符',
                'account.max' => '账号最多20个字符',
                'account.alpha_num' => '账号只能由字母和数字组成',
                'name.min' => '姓名至少2个字符',
                'name.max' => '姓名最多20个字符',
                'password.min' => '密码至少6个字符',
                'password.regex' => '密码必须同时包含英文字母和数字',
                'password_confirmation.same' => '两次输入的密码不一致',
                'email.required' => '邮箱不能为空',
                'email.email' => '邮箱格式不正确',
                'email.regex' => '仅支持QQ邮箱（@qq.com）',
                'email.max' => '邮箱最多100个字符',
                'email_code.required' => '邮箱验证码不能为空',
                'email_code.size' => '邮箱验证码必须是6位',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'message' => '验证失败',
                'data' => $e->errors()
            ], 422);
        }

        // 验证邮箱验证码
        $cacheKey = "email_code:{$validated['email']}:register";
        $cachedCode = cache()->get($cacheKey);

        if (is_null($cachedCode) || $cachedCode !== $validated['email_code']) {
            return response()->json([
                'code' => 400,
                'message' => '邮箱验证码无效或已过期',
                'data' => null
            ], 400);
        }
        cache()->forget($cacheKey);

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

            // ###########################
            // 第2处修改：强制写死角色为 student
            // ###########################
            'role' => 'student',  // 这里写死！永远不会是管理员！
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
     * 说明: 使用账号和密码登录
     */
    public function login(Request $request)
    {
        // 验证请求参数
        $validated = $request->validate([
            'account' => 'required|string',
            'password' => 'required|string',
        ]);

        // 1. 检查用户是否存在
        $user = \App\Models\User::where('account', $validated['account'])->first();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '账号不存在',
                'data' => null
            ], 401);
        }

        // 2. 验证密码
        $credentials = [
            'account' => $validated['account'],
            'password' => $validated['password'],
        ];

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'code' => 401,
                'message' => '密码错误',
                'data' => null
            ], 401);
        }

        // 3. 返回登录成功信息和token
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
            'email' => 'required|email|regex:/^[a-zA-Z0-9._%+-]+@qq\.com$/i',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:6|regex:/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]+$/',
            'password_confirmation' => 'required|string|same:password',
        ], [
            'email.regex' => '仅支持QQ邮箱（@qq.com）',
            'code.size' => '验证码必须是6位数字',
            'password.min' => '密码至少6个字符',
            'password.regex' => '密码必须同时包含英文字母和数字',
            'password_confirmation.same' => '两次输入的密码不一致',
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

        // 4. 验证邮箱验证码
        if (!$this->emailVerificationService->verifyCode($validated['email'], $validated['code'], 'reset_password')) {
            return response()->json([
                'code' => 400,
                'message' => '验证码错误或已过期',
                'data' => null
            ]);
        }

        // 5. 重置密码
        $user->update([
            'password' => $validated['password']
        ]);

        // 6. 返回成功
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
            $user->password = $validated['password'];
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

    /**
     * 发送邮箱验证码
     * 接口: POST /api/auth/send-email-code
     */
    public function sendEmailCode(Request $request)
    {
        // 确保返回 JSON
        $request->headers->set('Accept', 'application/json');

        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'type' => 'nullable|string|in:register,reset_password,bind',//验证码类型，注册，重置密码，绑定新邮箱等
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'message' => '验证失败',
                'data' => $e->errors()
            ], 422);
        }

        $email = $validated['email'];
        $type = $validated['type'] ?? 'register';

        // 根据不同类型进行额外验证
        switch ($type) {
            case 'register':
                // 注册时检查邮箱是否已被使用
                if (User::where('email', $email)->exists()) {
                    return response()->json([
                        'code' => 400,
                        'message' => '该邮箱已被注册',
                        'data' => null
                    ]);
                }
                break;

            case 'reset_password':
                // 重置密码时检查邮箱是否存在
                if (!User::where('email', $email)->exists()) {
                    return response()->json([
                        'code' => 400,
                        'message' => '该邮箱未注册',
                        'data' => null
                    ]);
                }
                break;
        }

        // 发送验证码
        try {
            $result = $this->emailVerificationService->sendCode($email, $type);

            if ($result['success']) {
                return response()->json([
                    'code' => 200,
                    'message' => $result['message'],
                    'data' => [
                        'expire_minutes' => $result['expire_minutes']
                    ]
                ]);
            }

            return response()->json([
                'code' => 400,
                'message' => $result['message'],
                'data' => null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '服务器错误: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

}
