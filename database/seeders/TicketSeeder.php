<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TicketSeeder extends Seeder
{
    public function run(): void
    {
        // ดึง user ที่มี role เป็น admin คนแรกจากระบบ
        $admin = User::where('role', 'admin')->first();

        // ถ้ายังไม่มี admin เลย ให้หยุด seeder นี้ (เพราะต้องใช้ admin เป็นเจ้าของ/ผู้รับผิดชอบ ticket ตัวอย่าง)
        if (!$admin) {
            return;
        }

        // สร้าง ticket ตัวอย่าง 1 ใบ (หรือดึงใบเดิมถ้า subject นี้มีอยู่แล้ว)
        $ticket = Ticket::firstOrCreate(
            // เงื่อนไขค้นหา ticket เดิม (ใช้ subject เป็นตัวระบุ)
            ['subject' => 'Unable to connect to Wi-Fi'],
            // ข้อมูลที่จะใช้สร้าง ticket หากยังไม่มี
            [
                'description' => 'After updating to Windows 11, the laptop cannot connect to Wi-Fi. Error shown: "No Internet."',
                'priority' => 'high',                                     // ความสำคัญสูง
                'impact' => 'high',                                       // ผลกระทบสูง
                'urgency' => 'high',                                      // เร่งด่วนสูง
                'channel' => 'portal',                                    // มาจากช่องทาง portal
                'assignment_group' => config('ticketing.assignment_groups.network'), // ส่งเข้ากลุ่ม Network
                'category' => 'network',                                  // จัดอยู่หมวด Network
                'status' => 'in_progress',                                // สถานะเริ่มต้น = กำลังดำเนินการ
                'sla_due_at' => Carbon::now()->addHours(4),               // เส้นตาย SLA (แก้ให้เสร็จใน 4 ชั่วโมง)
                'response_due_at' => Carbon::now()->addHour(),            // ต้องตอบกลับภายใน 1 ชั่วโมง
                'created_by' => $admin->id,                               // ผู้สร้าง ticket = admin
                'assigned_to' => $admin->id,                              // ผู้รับผิดชอบ ticket = admin
            ]
        );

        // ถ้า ticket นี้ยังไม่มีประวัติ status เลย ให้สร้าง status history เริ่มต้นให้ด้วย
        if ($ticket->statusHistories()->count() === 0) {
            TicketStatusHistory::create([
                'ticket_id' => $ticket->id,
                'event_type' => 'created',             // ประเภท event = created
                'from_status' => null,                 // ก่อนหน้าไม่มีสถานะ (null)
                'to_status' => 'in_progress',          // สถานะปัจจุบัน = in_progress
                'note' => 'Initial creation from seeder', // โน้ตว่าเกิดจาก seeder
                'metadata' => ['channel' => 'portal'], // metadata เสริม (เช่น channel)
                'created_by' => $admin->id,            // คนสร้าง event = admin
            ]);
        }

        // ถ้า ticket นี้ยังไม่มี comment เลย ให้ใส่ comment ตัวอย่างให้ 1 อัน
        if ($ticket->comments()->count() === 0) {
            TicketComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $admin->id,               // คนเขียนคอมเมนต์ = admin
                'visibility' => 'public',              // คอมเมนต์แบบ public ผู้ใช้มองเห็นได้
                'body' => 'We are investigating this connectivity issue.', // ข้อความตัวอย่าง
            ]);
        }
    }
}
