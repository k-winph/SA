<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // แสดงหน้ารายการการแจ้งเตือนของผู้ใช้
    public function index(Request $request)
    {
        // ดึง user ที่ล็อกอินอยู่จาก request
        $user = $request->user();

        // เอา notification ที่ยังไม่อ่านทั้งหมดของ user มาทำเครื่องหมายว่าอ่านแล้ว
        $user->unreadNotifications->markAsRead();

        // ดึง notification ทั้งหมดของ user เรียงจากใหม่ไปเก่า พร้อมแบ่งหน้า (หน้าละ 15 รายการ)
        $notifications = $user
            ->notifications()
            ->latest()
            ->paginate(15);

        // ส่งข้อมูลไปยัง view สำหรับแสดงรายการ notification
        return view('screens.notifications.index', [
            'notifications' => $notifications, // รายการการแจ้งเตือนของ user
            'activePage' => null,              // หน้านี้ไม่ได้ผูกกับเมนูหลักใดเป็นพิเศษ
        ]);
    }
}
