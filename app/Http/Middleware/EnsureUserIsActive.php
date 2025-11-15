<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    // middleware เอาไว้เช็คว่า user ที่ล็อกอินอยู่ ยังเป็น active อยู่ไหม
    public function handle(Request $request, Closure $next): Response
    {
        // ดึง user ปัจจุบันจาก request (ถ้าไม่ได้ล็อกอินจะเป็น null)
        $user = $request->user();

        // ถ้ามี user และ user นั้น "ไม่" active
        if ($user && !$user->isActive()) {
            // บังคับ logout ออกจากระบบก่อน
            Auth::logout();

            // ทำให้ session เดิมใช้ไม่ได้แล้ว
            $request->session()->invalidate();

            // ออก session token ใหม่เพื่อความปลอดภัย
            $request->session()->regenerateToken();

            // ข้อความแจ้งเตือนว่าแอคเคานต์ถูกปิดใช้งาน
            $message = 'Your account has been deactivated. Contact an administrator to restore access.';

            // ถ้า request นี้คาดหวังเป็น JSON (เช่น API) ให้ตอบ 403 กลับไปพร้อมข้อความ
            if ($request->expectsJson()) {
                abort(403, $message);
            }

            // ถ้าเป็นเว็บปกติ ให้ redirect กลับไปหน้า login พร้อม error ใต้ช่อง email
            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        // ถ้า user ยัง active ปกติ ก็ให้ผ่านไปทำขั้นตอนถัดไป
        return $next($request);
    }
}
