<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->comment('手机号');
            $table->string('code', 6)->comment('验证码');
            $table->string('type', 20)->default('register')->comment('验证码类型：register-注册');
            $table->timestamp('expired_at')->comment('过期时间');
            $table->boolean('is_used')->default(false)->comment('是否已使用');
            $table->timestamps();
            
            // 索引
            $table->index(['phone', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
    }
};