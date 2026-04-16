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
        // 创建管理员账号1--LX
        User::create([
            'account' => 'admin123',
            'name' => '系统管理员',
            'password' => 'admin124',  // 会自动哈希加密
            'role' => 'admin',
            'email' => '3258599349@qq.com',
        ]);

         // 创建管理员账号2--LZW
        User::create([
            'account' => 'admin124',
            'name' => '系统管理员',
            'password' => 'admin124',  // 会自动哈希加密
            'role' => 'admin',
            'email' => '193952040@qq.com',
        ]);

         // 创建管理员账号--WLJ
        User::create([
            'account' => 'admin125',
            'name' => '系统管理员',
            'password' => 'admin125',  // 会自动哈希加密
            'role' => 'admin',
            'email' => '2633681826@qq.com',
        ]);
        // 创建测试学生账号1
        User::create([
            'account' => '2021001',
            'name' => '张三',
            'password' => '123456',  // 会自动哈希加密
            'role' => 'student',
            'email' => 'zhangsan@example.com',
        ]);

    }
}
