<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketEventNotification extends Notification
{
    use Queueable; // ทำให้ Notification ตัวนี้สามารถใช้คิว (queue) ได้ ถ้าต้องการส่งแบบ async

    // รับ Ticket, ข้อความ, และ context เพิ่มเติมตอนสร้าง Notification
    public function __construct(
        protected Ticket $ticket,   // ticket ที่เกี่ยวข้องกับ notification นี้
        protected string $message,  // ข้อความหลักที่จะแสดงใน notification
        protected array $context = [] // ข้อมูล context เพิ่มเติม เช่น event_type
    ) {
    }

    // กำหนดช่องทางการส่ง Notification
    public function via($notifiable): array
    {
        // ส่งเก็บลงใน database อย่างเดียว (ใช้ผ่าน notifications table)
        return ['database'];
    }

    // ข้อมูลที่จะถูกบันทึกลงใน column `data` ของตาราง notifications
    public function toDatabase($notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,              // id ของ ticket
            'subject' => $this->ticket->subject,          // หัวข้อ ticket
            'status' => $this->ticket->status,            // สถานะปัจจุบันของ ticket
            'message' => $this->message,                  // ข้อความหลักของ notification
            'event_type' => $this->context['event_type'] ?? null, // ประเภท event เช่น created, assignment, comment
            'channel' => $this->ticket->channel,          // ช่องทางของ ticket (portal, email, api, ...)
            'priority' => $this->ticket->priority,        // ความสำคัญของ ticket
            'sla_due_at' => $this->ticket->sla_due_at,    // deadline SLA ถ้ามี
            // ข้อมูลของผู้ที่เป็นคนกระทำ (actor) ดึงจาก auth()->user() เอาเฉพาะ id กับ name
            'actor' => auth()->user()?->only(['id', 'name']),
            // ลิงก์ไปยังหน้ารายละเอียด ticket (ใช้ใน UI เวลากดดูจาก notification)
            'link' => route('tickets.show', $this->ticket),
        ];
    }
}
