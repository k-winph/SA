<?php

namespace App\Services\Ticket;

use App\Models\Ticket;
use App\Models\User;

class TicketMetricsService
{
    // สรุปจำนวน ticket แยกตามสถานะทั้งหมดในระบบ
    public function statusCounts(): array
    {
        // ดึง key ของสถานะทั้งหมดจาก config/ticketing.php
        $statusKeys = array_keys(config('ticketing.statuses', []));

        // ดึงจำนวน ticket แต่ละ status จากฐานข้อมูล
        $counts = Ticket::selectRaw('status, COUNT(*) as aggregate')
            ->whereIn('status', $statusKeys)
            ->groupBy('status')
            ->pluck('aggregate', 'status'); // ได้เป็นรูปแบบ [status => count]

        // map กลับเป็น array ที่พร้อมใช้ใน dashboard (มี status, label, color, value)
        return collect($statusKeys)
            ->map(fn ($status) => [
                'status' => $status,
                'label' => config("ticketing.statuses.{$status}.label") ?? ucfirst($status),
                'color' => config("ticketing.statuses.{$status}.color") ?? '#1f71ff',
                'value' => $counts[$status] ?? 0,
            ])
            ->values()
            ->all();
    }

    // นับจำนวน ticket ทั้งหมดในระบบ
    public function totalTickets(): int
    {
        return Ticket::count();
    }

    // นับจำนวน ticket ตามสถานะ (option: กรองตาม user ด้วย)
    public function countByStatus(string $status, ?User $user = null): int
    {
        $query = Ticket::query()->where('status', $status);

        // ถ้ามีส่ง user มา ให้กรองตามสิทธิ์ของ user นั้น
        if ($user) {
            // ถ้าเป็น agent (staff) ที่ไม่ใช่ admin -> ดูเฉพาะ ticket ที่ assign ให้ตัวเอง
            if ($user->isAgent() && !$user->isAdmin()) {
                $query->assignedTo($user);
            } else {
                // ถ้าเป็น user หรือ admin -> ใช้ scope ownedBy เพื่อตีความ "ของเขา"
                $query->ownedBy($user);
            }
        }

        return $query->count();
    }

    // คำนวณ MTTR (Mean Time To Resolve) เป็นชั่วโมง
    public function mttrInHours(): ?float
    {
        // ดึง ticket ที่มี resolved_at (เคยแก้เสร็จแล้ว)
        $resolved = Ticket::whereNotNull('resolved_at')->get();

        // ถ้ายังไม่มี ticket ที่ resolved เลย ให้คืนค่า null
        if ($resolved->isEmpty()) {
            return null;
        }

        // หาค่าเฉลี่ย "นาทีที่ใช้" ตั้งแต่สร้างจน resolved
        $minutes = $resolved->avg(fn (Ticket $ticket) => $ticket->created_at->diffInMinutes($ticket->resolved_at));

        // แปลงเป็นชั่วโมง (ปัดเลขทศนิยม 2 ตำแหน่ง)
        return round($minutes / 60, 2);
    }

    // นับจำนวน ticket ที่ breach SLA ไปแล้ว (is_sla_breached = true)
    public function breachedSlaCount(): int
    {
        return Ticket::where('is_sla_breached', true)->count();
    }

    // สรุปจำนวน ticket คงค้าง (backlog) แยกตาม priority
    public function backlogByPriority(): array
    {
        $priorities = ['high', 'normal', 'low'];

        // ดึงจำนวน ticket แต่ละ priority
        $counts = Ticket::selectRaw('priority, COUNT(*) as aggregate')
            ->whereIn('priority', $priorities)
            ->groupBy('priority')
            ->pluck('aggregate', 'priority');

        // คืนค่าเป็น array [{ priority: 'high', value: X }, ...]
        return collect($priorities)
            ->map(fn ($priority) => [
                'priority' => $priority,
                'value' => $counts[$priority] ?? 0,
            ])
            ->values()
            ->all();
    }
}
