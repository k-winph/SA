<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    // seeder นี้เอาไว้สร้าง (หรืออัปเดต) user ที่เป็น Admin เริ่มต้นในระบบ
    public function run(): void
    {
        // updateOrCreate = ถ้ามี email นี้แล้วให้อัปเดต, ถ้าไม่มีให้สร้างใหม่
        User::updateOrCreate(
            // เงื่อนไขในการหา record เดิม (ใช้ email เป็นตัวระบุ)
            ['email' => 'admin@example.com'],
            // ข้อมูลที่จะถูกสร้าง/อัปเดต
            [
                'name' => 'System Admin',              // ชื่อแสดงของ admin
                'password' => Hash::make('admin123456'), // รหัสผ่าน (ถูก hash ด้วย bcrypt ผ่าน Hash::make)
                'role' => User::ROLE_ADMIN,           // กำหนด role ให้เป็น admin
                'is_active' => true,                  // ให้ account นี้อยู่ในสถานะ active
                'deactivated_at' => null,             // ยังไม่เคยถูก deactivate
            ]
        );
    }
}
