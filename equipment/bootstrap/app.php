<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API 路由认证失败时返回 JSON 而不是重定向
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'code' => 401,
                    'message' => '未登录或token无效',
                    'data' => null
                ], 401);
            }
        });

        // 处理 JWT 相关异常
        $exceptions->renderable(function (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e, \Illuminate\Http\Request $request) {
            return response()->json([
                'code' => 401,
                'message' => 'Token 无效',
                'data' => null
            ], 401);
        });

        $exceptions->renderable(function (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e, \Illuminate\Http\Request $request) {
            return response()->json([
                'code' => 401,
                'message' => 'Token 已过期',
                'data' => null
            ], 401);
        });

        $exceptions->renderable(function (\Tymon\JWTAuth\Exceptions\JWTException $e, \Illuminate\Http\Request $request) {
            return response()->json([
                'code' => 401,
                'message' => 'Token 未提供或无效: ' . $e->getMessage(),
                'data' => null
            ], 401);
        });
    })->create();
