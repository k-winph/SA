<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * รายชื่อ input ที่ห้ามถูก flash กลับไปที่ session ตอน validate ไม่ผ่าน
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * เมธอดสำหรับลงทะเบียนการจัดการ exception ต่าง ๆ ของแอป
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // กำหนดวิธีตอบกลับเวลาเจอ MethodNotAllowedHttpException
        $this->renderable(function (MethodNotAllowedHttpException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'The requested HTTP method is not allowed for this endpoint.',
                ], 405);
            }

            // ถ้าไม่ใช่ JSON (เป็นหน้าเว็บปกติ) กำหนดหน้าที่จะ fallback
            $fallback = auth()->check() ? route('dashboard.admin') : route('login');

            if ($request->headers->has('referer')) {
                return redirect()->back();
            }

            return redirect()->to($fallback);
        });
    }
}
