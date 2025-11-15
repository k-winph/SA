<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    // แสดงหน้าแบบฟอร์มเปลี่ยนรหัสผ่านของ user ปัจจุบัน
    public function editPassword()
    {
        return view('screens.account.password', [
            'activePage' => 'account-password',
        ]);
    }

    // รับฟอร์มเปลี่ยนรหัสผ่าน และอัปเดตรหัสผ่านในระบบ
    public function updatePassword(Request $request)
    {
        // ตรวจสอบข้อมูลที่ส่งมาจากฟอร์ม
        $validated = $request->validate([
            // ต้องกรอกรหัสผ่านปัจจุบัน และใช้ rule current_password
            // เพื่อตรวจว่าตรงกับรหัสผ่านเดิมในระบบหรือไม่
            'current_password' => ['required', 'current_password'],

            // password ใหม่: ต้องกรอก, เป็น string, ยาวอย่างน้อย 8 ตัว
            // และต้องมี field password_confirmation ที่ตรงกัน (confirmed)
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // ดึง user ที่กำลัง login อยู่ตอนนี้
        $user = $request->user();

        // เข้ารหัส (hash) password ใหม่ก่อนเก็บลงฐานข้อมูล
        $user->password = Hash::make($validated['password']);
        $user->save(); // บันทึกลงฐานข้อมูล

        // redirect กลับไปหน้าเดิม พร้อม flash message แจ้งว่าทำสำเร็จ
        return back()->with('status', 'Password updated successfully.');
    }
}
