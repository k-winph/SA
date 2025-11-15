<?php

namespace App\Support\Concerns;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketEventNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

trait NotifiesTicketStakeholders
{
    // หา "ผู้มีส่วนเกี่ยวข้อง" กับ ticket ที่จะถูกแจ้งเตือน (stakeholders)
    // ในระบบนี้จำกัดให้เป็น Admin ที่ยัง active เท่านั้น
    protected function ticketStakeholders(Ticket $ticket): Collection
    {
        // จำกัดให้ส่ง broadcast notification เฉพาะ Admin ที่ยัง active และไม่ใช่คนที่กำลังทำ action นี้
        return User::query()
            ->where('role', User::ROLE_ADMIN)   // เฉพาะ role admin
            ->where('is_active', true)          // ต้องยัง active อยู่
            ->where('id', '!=', Auth::id())     // ไม่รวมตัวเอง (คนที่กดเปลี่ยน)
            ->get();
    }

    // ส่ง Notification ให้กับ stakeholders ของ ticket
    protected function notifyStakeholders(Ticket $ticket, string $message, array $context = []): void
    {
        // ดึงรายชื่อผู้รับแจ้งเตือน
        $recipients = $this->ticketStakeholders($ticket);

        // ถ้าไม่มีคนรับเลย ก็ไม่ต้องทำอะไร
        if ($recipients->isEmpty()) {
            return;
        }

        // ส่ง TicketEventNotification ไปให้ทุกคนใน recipients
        Notification::send($recipients, new TicketEventNotification($ticket, $message, $context));
    }
}
