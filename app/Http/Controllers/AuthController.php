<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class AuthController extends Controller
{
    // แสดงหน้า Login ถ้าล็อกอินแล้วให้เด้งไปหน้า dashboard เลย
    public function showLogin()
    {
        // ถ้ามีการล็อกอินอยู่แล้ว ไม่ต้องให้เห็นหน้า login ซ้ำ
        if (Auth::check()) {
            return redirect()->route('dashboard.admin');
        }

        // แสดงหน้าแบบฟอร์มล็อกอิน
        return view('screens.auth.login');
    }

    // จัดการคำขอล็อกอินจากฟอร์ม
    public function login(Request $request)
    {
        // validate ข้อมูล email / password จากฟอร์ม
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // ดึง user จาก email ที่กรอกมา
        $user = User::where('email', $credentials['email'])->first();

        // ถ้าพบ user แต่สถานะไม่ active ให้บล็อกไม่ให้ล็อกอิน
        if ($user && !$user->isActive()) {
            return back()->withErrors([
                'email' => 'This account is currently deactivated. Please contact an administrator.',
            ])->onlyInput('email'); // ให้กรอก email เดิมค้างไว้
        }

        // ถ้าไม่ได้ติ๊ก remember me ให้ลบ cookie remember me เดิมออก
        if (!$request->boolean('remember')) {
            Cookie::queue(Cookie::forget(Auth::getRecallerName()));
        }

        // พยายามล็อกอินด้วย credentials ที่ validate แล้ว + ตัวเลือก remember me
        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            // ถ้าล็อกอินไม่ผ่าน ให้ส่ง error กลับไป
            return back()->withErrors([
                'email' => 'Invalid credentials provided.',
            ])->onlyInput('email');
        }

        // ล็อกอินสำเร็จ -> regenerate session id ป้องกัน session fixation
        $request->session()->regenerate();

        // ส่งไปยังหน้าที่ตั้งใจเข้าก่อนล็อกอิน (ถ้ามี) ไม่งั้นไป dashboard.admin
        return redirect()->intended(route('dashboard.admin'));
    }

    // จัดการ logout ผู้ใช้
    public function logout(Request $request)
    {
        // ออกจากระบบ
        Auth::logout();

        // ลบ cookie remember me ทิ้ง
        Cookie::queue(Cookie::forget(Auth::getRecallerName()));

        // ทำให้ session เดิมใช้การไม่ได้ แล้วออก token ใหม่
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // กลับไปหน้า login
        return redirect()->route('login');
    }
}
