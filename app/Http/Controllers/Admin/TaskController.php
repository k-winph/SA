<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TicketEventNotification;

class TaskController extends Controller
{
    // หน้าไว้ให้แอดมินจัดการ Ticket (มองเป็น Task ที่ต้องทำ)
    public function index(Request $request)
    {
        // ดึง Ticket ทั้งหมดมาเรียงจากล่าสุดไปเก่า
        $query = Ticket::query()->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        // ดึง ticket พร้อมความสัมพันธ์ creator, assignee + แบ่งหน้า paginate
        $tickets = $query->with(['creator', 'assignee'])->paginate(10)->withQueryString();

        $staff = User::where('role', User::ROLE_STAFF)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        // ส่งข้อมูลไปยัง view สำหรับหน้า manage_tasks ของ admin
        return view('screens.admin.manage_tasks', [
            'activePage' => 'manage-tasks',
            'tickets' => $tickets,
            'staff' => $staff,
        ]);
    }

    // ฟังก์ชันให้แอดมิน assign ticket ให้ staff คนใดคนหนึ่ง
    public function assign(Request $request, Ticket $ticket)
    {
        // ตรวจสอบข้อมูลที่ส่งมา (ต้องมี assignee_id และต้องเป็น user ที่มีอยู่จริง)
        $validated = $request->validate([
            'assignee_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        // ดึงข้อมูล user ของคนที่จะถูก assign
        $assignee = User::findOrFail($validated['assignee_id']);

        // ถ้า user นี้ไม่ใช่ role staff ให้เด้งกลับพร้อม error
        if (!$assignee->isStaff()) {
            return back()->withErrors(['assignee_id' => 'Assignee must be a staff user.']);
        }

        // อัปเดท ticket: กำหนดคนรับผิดชอบ และจัดการสถานะ
        $ticket->forceFill([
            'assigned_to' => $assignee->id,
            // ถ้า ticket ยังไม่อยู่ในสถานะ done/resolved/closed ให้เปลี่ยนเป็น in_progress
            'status' => in_array($ticket->status, ['done', 'resolved', 'closed'], true)
                ? $ticket->status
                : 'in_progress',
        ])->save();

        // ส่ง Notification ให้ staff ที่ถูก assign แจ้งว่ามี ticket ใหม่
        Notification::send(
            $assignee,
            new TicketEventNotification(
                $ticket,
                'You have been assigned a ticket.',
                ['event_type' => 'assignment']
            )
        );

        return back()->with('status', 'Ticket assigned to ' . $assignee->name . '.');
    }
}
