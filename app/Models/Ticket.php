<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    // ระบุ field ที่อนุญาตให้กรอก/อัปเดตผ่าน mass assignment (create(), update(), fill())
    protected $fillable = [
        'subject',             // หัวข้อปัญหา
        'description',         // รายละเอียดปัญหา
        'priority',            // ระดับความสำคัญ (low / normal / high)
        'category',            // หมวดหมู่ปัญหา (network, hardware, ฯลฯ)
        'attachment_path',     // path ไฟล์แนบ (ถ้ามี)
        'status',              // สถานะ ticket (open, in_progress, resolved, closed, ...)
        'created_by',          // ผู้สร้าง ticket (user id)
        'assigned_to',         // ผู้รับผิดชอบ ticket (staff id)
        'assignment_group',    // กลุ่มที่รับผิดชอบ (ถ้าใช้แบบ group)
        'channel',             // ช่องทางที่ ticket เข้ามา (portal, email, api, ...)
        'impact',              // ระดับผลกระทบ (low / medium / high)
        'urgency',             // ระดับความเร่งด่วน (low / medium / high)
        'ingestion_reference', // ref ของระบบภายนอก ที่ใช้สร้าง ticket
        'sla_due_at',          // กำหนดเส้นตาย SLA ที่ต้องแก้ให้เสร็จ
        'is_sla_breached',     // flag บอกว่า SLA ถูกละเมิดแล้วหรือยัง
        'response_due_at',     // เวลาที่ควรตอบกลับครั้งแรกตาม SLA
        'first_response_at',   // เวลา agent ตอบกลับครั้งแรกจริง ๆ
        'resolved_at',         // เวลาที่แก้ปัญหาเสร็จ (resolved)
        'closed_at',           // เวลาที่ปิด ticket จริง
        'escalated_at',        // เวลาที่มีการ escalate ปัญหา
    ];

    // กำหนดให้ฟิลด์บางตัว cast เป็นชนิด datetime / boolean อัตโนมัติ
    protected $casts = [
        'sla_due_at' => 'datetime',
        'response_due_at' => 'datetime',
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'escalated_at' => 'datetime',
        'is_sla_breached' => 'boolean',
    ];

    // ความสัมพันธ์ไปยัง user ที่สร้าง ticket
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ความสัมพันธ์ไปยัง user ที่รับผิดชอบ ticket (assignee)
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // ความสัมพันธ์กับตาราง history ของสถานะ ticket
    public function statusHistories()
    {
        return $this->hasMany(TicketStatusHistory::class)->latest();
    }

    // ความสัมพันธ์กับ comment ทั้งหมดของ ticket นี้
    public function comments()
    {
        return $this->hasMany(TicketComment::class)->latest();
    }

    // scope: ใช้กรอง ticket ที่ "เป็นของ" user นั้น ๆ
    // ถ้าเป็น agent/admin ให้เห็นได้หมด ถ้าเป็น user ปกติให้เห็นเฉพาะที่ตัวเองสร้าง
    public function scopeOwnedBy($query, User $user)
    {
        if ($user->isAgent() || $user->isAdmin()) {
            return $query;
        }

        return $query->where('created_by', $user->id);
    }

    // scope: กรองตาม status (ถ้าไม่ส่ง status มา ให้คืน query เดิม)
    public function scopeStatus($query, ?string $status)
    {
        if (!$status) {
            return $query;
        }

        return $query->where('status', $status);
    }

    // scope: กรอง ticket ที่ assign ให้ user คนหนึ่ง ๆ
    public function scopeAssignedTo($query, User $user)
    {
        return $query->where('assigned_to', $user->id);
    }

    // ดึง definition ของสถานะปัจจุบันจาก config/ticketing.php (statuses)
    // ถ้าไม่มีใน config ให้ใช้ค่า default label, color, kanban_column
    public function getStatusDefinition(): array
    {
        return config("ticketing.statuses.{$this->status}", [
            'label' => ucfirst(str_replace('_', ' ', $this->status)), // แปลงชื่อ status เป็น label สวย ๆ
            'color' => '#1f71ff',                                      // สี default
            'kanban_column' => 'to_do',                                // คอลัมน์ default สำหรับ Kanban
        ]);
    }

    // accessors: เรียกใช้เป็น $ticket->status_label เพื่อเอา label ของสถานะมาใช้ใน view
    public function getStatusLabelAttribute(): string
    {
        return $this->getStatusDefinition()['label'];
    }
}
