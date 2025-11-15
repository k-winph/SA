<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    // สิทธิ์ในการดูรายการ Ticket ทั้งหมด (เช็คแค่ว่า login แล้วก็ ok)
    public function viewAny(User $user): bool
    {
        return true;
    }

    // สิทธิ์ในการดู Ticket รายตัว
    public function view(User $user, Ticket $ticket): bool
    {
        // ถ้าเป็น admin หรือ agent (staff) ให้ดูได้ทุกใบ
        if ($user->isAdmin() || $user->isAgent()) {
            return true;
        }

        // ถ้าเป็นผู้ใช้ทั่วไป ดูได้เฉพาะ ticket ที่ตัวเองเป็นคนสร้าง
        return $ticket->created_by === $user->id;
    }

    // สิทธิ์ในการสร้าง Ticket ใหม่
    public function create(User $user): bool
    {
        // ไม่อนุญาตให้ agent (staff) สร้าง ticket (ถือว่าเป็นคนรับงาน ไม่ใช่คนแจ้งงาน)
        return !$user->isAgent();
    }

    // สิทธิ์ในการแก้ไข Ticket
    public function update(User $user, Ticket $ticket): bool
    {
        // อนุญาตให้เฉพาะ agent/staff แก้ไข ticket (เช่น เปลี่ยนสถานะ, priority)
        return $user->isAgent();
    }

    // สิทธิ์ในการลบ Ticket
    public function delete(User $user, Ticket $ticket): bool
    {
        // ลบได้เฉพาะ admin
        return $user->isAdmin();
    }

    // สิทธิ์ในการคอมเมนต์ใน Ticket
    public function comment(User $user, Ticket $ticket): bool
    {
        // ถ้า ticket ปิดแล้ว (closed) ห้ามคอมเมนต์เพิ่ม
        if ($ticket->status === 'closed') {
            return false;
        }

        // ถ้าเป็น agent/staff ให้คอมเมนต์ได้ทุกใบ
        if ($user->isAgent()) {
            return true;
        }

        // ถ้าเป็น user ปกติ ให้คอมเมนต์ได้เฉพาะ ticket ที่ตัวเองเป็นคนสร้าง
        return $ticket->created_by === $user->id;
    }

    // สิทธิ์ในการเพิ่ม internal note (คอมเมนต์ภายในที่ user ทั่วไปมองไม่เห็น)
    public function addInternalNote(User $user, Ticket $ticket): bool
    {
        // ให้เฉพาะ agent/staff ใช้ internal note
        return $user->isAgent();
    }

    // สิทธิ์ในการจัดการ assignment (กำหนด/เปลี่ยนคนรับผิดชอบ)
    public function manageAssignments(User $user, Ticket $ticket): bool
    {
        // ให้เฉพาะ agent/staff เท่านั้นที่จัดการ assignment ได้
        return $user->isAgent();
    }

    // สิทธิ์ในการดูข้อมูลสำคัญ/ละเอียด (sensitive data) ใน Ticket
    public function viewSensitiveData(User $user, Ticket $ticket): bool
    {
        // จำกัดให้ดูได้เฉพาะ agent/staff
        return $user->isAgent();
    }
}
