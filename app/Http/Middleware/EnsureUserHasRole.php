<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    // middleware สำหรับเช็คว่า user มี role ที่กำหนดหรือไม่
    // $roles รับมาเป็นรายการ role เช่น ('admin', 'staff')
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // ดึง user ที่ล็อกอินอยู่จาก request
        $user = $request->user();

        // ถ้ามี user และ user มี role ใด role หนึ่งตรงกับที่กำหนดไว้
        if ($user && $user->hasAnyRole($roles)) {
            // ให้ผ่านไปทำ middleware/logic ถัดไปได้ตามปกติ
            return $next($request);
        }

        // ถ้าไม่มีสิทธิ์ (ไม่มี role ตรงตามที่กำหนด) ให้ตอบกลับ 403 Forbidden
        abort(403, 'You are not authorized to access this resource.');
    }
}
