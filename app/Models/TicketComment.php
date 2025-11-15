<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketComment extends Model
{
    use HasFactory;

    // ระบุ field ที่อนุญาตให้กรอก/อัปเดตแบบ mass assignment
    protected $fillable = [
        'ticket_id',   // อ้างอิงไปยัง ticket ที่คอมเมนต์นี้อยู่
        'user_id',     // ผู้เขียนคอมเมนต์ (user คนไหน)
        'visibility',  // ระดับการมองเห็นของคอมเมนต์ (เช่น public / internal)
        'body',        // เนื้อหาคอมเมนต์
    ];

    // ความสัมพันธ์: คอมเมนต์นี้เป็นของ Ticket ไหน
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    // ความสัมพันธ์: คนเขียนคอมเมนต์เป็น User คนไหน
    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // scope: จำกัดคอมเมนต์ที่ผู้ใช้คนหนึ่ง "มองเห็น" ได้
    // - ถ้าเป็น agent/staff: เห็นทุกคอมเมนต์ (public + internal)
    // - ถ้าเป็น user ปกติ: เห็นเฉพาะ visibility = 'public'
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isAgent()) {
            // agent เห็นทั้งหมด ไม่ต้องกรอง
            return $query;
        }

        // user ปกติ เห็นเฉพาะ public comment
        return $query->where('visibility', 'public');
    }
}
