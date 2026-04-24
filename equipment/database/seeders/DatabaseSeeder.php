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
        // 先填充设备分类
        $this->call(CategorySeeder::class);


        // 创建管理员账号1--LX
        

        // 创建管理员账号2--LZW
        User::firstOrCreate(
            ['account' => 'admin124'],
            [
                'name' => '系统管理员1',
                'password' => 'admin124',
                'role' => 'admin',
                'email' => '193952040@qq.com',
            ]
        );

        // 创建管理员账号--WLJ
        User::firstOrCreate(
            ['account' => 'admin125'],
            [
                'name' => '系统管理员2',
                'password' => 'admin125',
                'role' => 'admin',
                'email' => '2633681826@qq.com',
            ]
        );

      

        // 创建测试学生账号1
        User::firstOrCreate(
            ['account' => '25010420521'],
            [
                'name' => '李四',
                'password' => 'F123457',
                'role' => 'student',
                'email' => '3258599349@qq.com',
            ]
        );

    }
}
