<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketStatusHistory extends Model
{
    use HasFactory;

    // ฟิลด์ที่อนุญาตให้กรอก/อัปเดตแบบ mass assignment
    protected $fillable = [
        'ticket_id',   // อ้างอิงไปยัง ticket ที่มีการเปลี่ยนสถานะ
        'event_type',  // ประเภทเหตุการณ์ เช่น status_change, assignment, comment, ingested ฯลฯ
        'from_status', // สถานะเดิมก่อนเปลี่ยน
        'to_status',   // สถานะใหม่หลังเปลี่ยน
        'note',        // ข้อความอธิบาย/หมายเหตุของการเปลี่ยนสถานะ
        'metadata',    // ข้อมูลเสริมอื่น ๆ รูปแบบ JSON (เก็บเป็น array)
        'created_by',  // user ที่เป็นคนสร้าง event นี้ (actor)
    ];

    // กำหนดให้ metadata ถูก cast เป็น array อัตโนมัติ ตอนดึง/บันทึก
    protected $casts = [
        'metadata' => 'array',
    ];

    // ความสัมพันธ์: history นี้เกี่ยวข้องกับ Ticket ตัวไหน
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    // ความสัมพันธ์: ใครเป็นคนทำ action นี้ (actor)
    public function actor()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
