<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    // middleware สำหรับเช็คว่า user ที่กำลังใช้งานอยู่เป็น "admin" หรือไม่
    public function handle(Request $request, Closure $next): Response
    {
        // ดึง user ที่ล็อกอินอยู่จาก request (ถ้าไม่ได้ล็อกอินจะเป็น null)
        $user = $request->user();

        // ถ้าไม่มี user (ยังไม่ล็อกอิน) หรือ user นั้นไม่ใช่ admin
        if (!$user || !$user->isAdmin()) {
            // ตอบกลับด้วย HTTP 403 Forbidden พร้อมข้อความว่าให้เฉพาะ admin เท่านั้น
            abort(403, 'Only administrators can perform this action.');
        }

        // ถ้าเป็น admin ให้ผ่านไปยัง middleware หรือ action ถัดไป
        return $next($request);
    }
}
