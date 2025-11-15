<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Notifications\TicketEventNotification;
use App\Services\Ticket\TicketAutomationService;
use App\Support\Concerns\NotifiesTicketStakeholders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class TicketCommentController extends Controller
{
    // ใช้ trait สำหรับช่วยแจ้งเตือนผู้ที่เกี่ยวข้องกับ Ticket (stakeholders)
    use NotifiesTicketStakeholders;

    // ฉีด (inject) TicketAutomationService เข้ามาใช้ใน Controller นี้
    public function __construct(protected TicketAutomationService $automation)
    {
    }

    // สร้างคอมเมนต์ใหม่ใน Ticket
    public function store(Request $request, Ticket $ticket)
    {
        // ตรวจสิทธิ์ก่อน ว่าผู้ใช้มีสิทธิ์ comment บน ticket นี้ไหม (ใช้ policy 'comment')
        $this->authorize('comment', $ticket);

        // ตรวจสอบค่าที่ส่งมา ต้องมี body เป็น string
        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        // สร้าง comment ใหม่ผูกกับ ticket ที่กำหนด และ user ปัจจุบัน
        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'body' => $validated['body'],
            'visibility' => 'public', // ตอนนี้กำหนดให้เป็น public comment
        ]);

        // log event ว่ามีการ comment ลงบน ticket
        $this->automation->logEvent($ticket, 'comment', [
            'note' => $validated['body'],
            'metadata' => ['visibility' => 'public'],
        ]);

        // ดึง actor (คนที่ทำ action นี้) คือ user ปัจจุบัน
        $actor = Auth::user();

        // สร้างข้อความสั้น ๆ สำหรับแจ้งเตือนผู้เกี่ยวข้อง
        $message = sprintf(
            'New %s comment from %s',
            ucfirst($comment->visibility), // แปลง visibility ตัวแรกเป็นตัวใหญ่ (Public)
            $actor->name
        );

        // แจ้งเตือน stakeholders ของ ticket (เช่น creator, assignee, admin ตาม logic ใน trait)
        $this->notifyStakeholders($ticket, $message, ['event_type' => 'comment']);

        // payload พื้นฐานที่จะแนบไปใน Notification
        $payload = [
            'event_type' => 'comment',
            'note' => $comment->body,
        ];

        // ถ้าคนที่คอมเมนต์เป็น agent/staff
        if ($actor->isAgent()) {
            // ถ้า ticket มี creator และ creator ยัง active ให้ส่ง notification หา creator ว่ามี staff มาตอบ
            if ($ticket->creator && $ticket->creator->isActive()) {
                Notification::send($ticket->creator, new TicketEventNotification(
                    $ticket,
                    'Support staff added a comment on your ticket.',
                    $payload
                ));
            }
        }
        // ถ้าคนที่คอมเมนต์ไม่ใช่ agent แสดงว่าเป็น user ปกติ
        // ให้แจ้งเตือน assignee (คนรับผิดชอบ ticket) ถ้าเขายัง active อยู่
        elseif ($ticket->assignee && $ticket->assignee->isActive()) {
            Notification::send($ticket->assignee, new TicketEventNotification(
                $ticket,
                'A user added a comment to the ticket you own.',
                $payload
            ));
        }

        // ถ้าคนที่คอมเมนต์เป็น agent ให้ mark ว่า ticket นี้ถูกตอบกลับแล้ว (responded)
        if ($request->user()->isAgent()) {
            $this->automation->markResponded($ticket);
        }

        // อัปเดตสถานะ SLA ว่าละเมิด SLA หรือยัง (เช็กเวลาตามเงื่อนไข SLA)
        $this->automation->refreshSlaBreachState($ticket);

        // กลับไปหน้าเดิม พร้อมข้อความแจ้งเตือนว่าเพิ่มคอมเมนต์แล้ว
        return back()->with('status', 'Comment added.');
    }

    // แก้ไขคอมเมนต์ของ Ticket
    public function update(Request $request, Ticket $ticket, TicketComment $comment)
    {
        // ตรวจสิทธิ์ก่อน ว่าสามารถ comment บน ticket นี้ได้ไหม
        $this->authorize('comment', $ticket);

        // กันกรณีมีคนพยายามแก้คอมเมนต์ที่ไม่ได้อยู่ใน ticket นี้ (URL ถูกแก้)
        if ($comment->ticket_id !== $ticket->id) {
            abort(404); // หาไม่เจอ / ไม่ให้เข้าถึง
        }

        $user = $request->user();

        // อนุญาตให้แก้ได้เฉพาะเจ้าของคอมเมนต์เท่านั้น
        if ($comment->user_id !== $user->id) {
            abort(403); // ห้ามทำ (Forbidden)
        }

        // ตรวจสอบ body ใหม่ที่ส่งมา
        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        // อัปเดตข้อความในคอมเมนต์
        $comment->update([
            'body' => $validated['body'],
            // visibility ยังคงค่าเดิมไว้ ไม่เปลี่ยน
            'visibility' => $comment->visibility,
        ]);

        // log event ว่ามีการแก้ไขคอมเมนต์
        $this->automation->logEvent($ticket, 'comment_edit', [
            'note' => $validated['body'],
            'metadata' => [
                'visibility' => $comment->visibility,
                'comment_id' => $comment->id,
            ],
        ]);

        // แจ้ง stakeholders ว่าคอมเมนต์ถูกแก้ไขโดยใคร
        $this->notifyStakeholders(
            $ticket,
            sprintf('Comment updated by %s', Auth::user()->name),
            ['event_type' => 'comment_update']
        );

        // กลับไปหน้าเดิม พร้อมข้อความแจ้งเตือนว่าอัปเดตคอมเมนต์แล้ว
        return back()->with('status', 'Comment updated.');
    }
}
