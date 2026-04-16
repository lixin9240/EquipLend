<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 创建管理员账号
        User::create([
            'account' => 'admin123',
            'name' => '系统管理员',
            'password' => 'admin123',  // 会自动哈希加密
            'role' => 'admin',
            'email' => '3258599349@qq.com',
        ]);

        // 创建测试学生账号1
        User::create([
            'account' => '2021001',
            'name' => '张三',
            'password' => '123456',  // 会自动哈希加密
            'role' => 'student',
            'email' => 'zhangsan@example.com',
        ]);

        // 创建测试学生账号2
        User::create([
            'account' => '2021002',
            'name' => '李四',
            'password' => '123456',  // 会自动哈希加密
            'role' => 'student',
            'email' => 'lisi@example.com',
        ]);
    }
}
