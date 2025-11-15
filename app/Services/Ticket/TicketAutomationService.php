<?php

namespace App\Services\Ticket;

use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TicketAutomationService
{
    // เติม (enrich) ข้อมูลให้ payload ของ ticket ก่อนสร้าง/อัปเดต
    public function enrichPayload(array $payload): array
    {
        // ตั้งค่า default ถ้าไม่ส่งมา
        $payload['channel'] = $payload['channel'] ?? 'portal';
        $payload['impact'] = $payload['impact'] ?? 'medium';
        $payload['urgency'] = $payload['urgency'] ?? 'medium';

        // ถ้าไม่ได้กำหนด priority ให้ใช้ฟังก์ชันคำนวณตาม impact + urgency
        $payload['priority'] = $payload['priority']
            ?? $this->determinePriority($payload['impact'], $payload['urgency']);

        // กำหนดสถานะเริ่มต้นของ ticket (เช่น open) จาก config ถ้าไม่มีใน payload
        $payload['status'] = $payload['status'] ?? config('ticketing.default_status', 'open');

        // เดา category จาก subject + description ถ้ายังไม่กำหนด
        $payload['category'] = $payload['category']
            ?? $this->guessCategory($payload['subject'] ?? '', $payload['description'] ?? '');

        // กำหนด assignment_group ตาม category ถ้ายังไม่กำหนด
        $payload['assignment_group'] = $payload['assignment_group']
            ?? $this->determineAssignmentGroup($payload['category']);

        // ถ้า assigned_to เป็นค่าว่าง ให้เซ็ตเป็น null ชัดๆ
        if (empty($payload['assigned_to'])) {
            $payload['assigned_to'] = null;
        }

        // คำนวณเวลาตาม SLA (response_due_at / sla_due_at) จาก priority
        $payload = array_merge($payload, $this->determineSlaWindows($payload['priority']));

        return $payload;
    }

    // คำนวณ priority จาก impact + urgency โดยใช้การให้คะแนน
    public function determinePriority(string $impact, string $urgency): string
    {
        // แปลงค่าข้อความให้เป็นคะแนน
        $scores = [
            'low' => 1,
            'medium' => 2,
            'normal' => 2,
            'high' => 3,
        ];

        // รวมคะแนน impact + urgency
        $score = ($scores[$impact] ?? 2) + ($scores[$urgency] ?? 2);

        // แปลงคะแนนรวมเป็น priority
        return match (true) {
            $score >= 5 => 'high',
            $score >= 3 => 'normal',
            default => 'low',
        };
    }

    // กำหนดช่วงเวลา SLA ตาม priority (ตอบครั้งแรก / ปิดเคส)
    public function determineSlaWindows(string $priority): array
    {
        // ดึง config ของ SLA ตามระดับ priority ถ้าไม่มีใช้ normal แทน
        $slaConfig = config("ticketing.sla.{$priority}")
            ?? config('ticketing.sla.normal');

        $responseMinutes = $slaConfig['response_minutes'] ?? 240;   // เวลาต้องตอบครั้งแรก
        $resolutionMinutes = $slaConfig['resolution_minutes'] ?? 1440; // เวลาต้องแก้ให้เสร็จ

        $now = now();

        return [
            'response_due_at' => $now->copy()->addMinutes($responseMinutes),
            'sla_due_at' => $now->copy()->addMinutes($resolutionMinutes),
            'is_sla_breached' => false, // เริ่มต้นยังไม่ breach
        ];
    }

    // กำหนด assignment_group จาก category
    public function determineAssignmentGroup(?string $category): ?string
    {
        if (!$category) {
            return null;
        }

        // หาจาก config ตามชื่อ category ถ้าไม่มีใช้ group 'other'
        return config("ticketing.assignment_groups.{$category}")
            ?? config('ticketing.assignment_groups.other');
    }

    // พยายามเดา category จาก keyword ใน subject + description
    public function guessCategory(string $subject, string $description): ?string
    {
        $haystack = Str::lower($subject . ' ' . $description);

        // กฎง่าย ๆ: map คำที่เจอบ่อยๆ ไปเป็น category
        $rules = [
            'network' => ['network', 'wifi', 'vpn', 'router', 'latency'],
            'hardware' => ['laptop', 'keyboard', 'mouse', 'hardware', 'battery', 'device'],
            'software' => ['software', 'bug', 'crash', 'app', 'application', 'update'],
            'access' => ['access', 'login', 'password', 'account', 'unlock'],
        ];

        foreach ($rules as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (Str::contains($haystack, $keyword)) {
                    return $category;
                }
            }
        }

        // ถ้าไม่เข้าเงื่อนไขไหนเลย ให้คืนค่า null
        return null;
    }

    // บันทึก event ลง TicketStatusHistory (เช่น created, status_change, assignment, comment ฯลฯ)
    public function logEvent(Ticket $ticket, string $eventType, array $context = []): TicketStatusHistory
    {
        return TicketStatusHistory::create([
            'ticket_id' => $ticket->id,
            'event_type' => $eventType,
            'from_status' => $context['from_status'] ?? null,
            'to_status' => $context['to_status'] ?? $ticket->status,
            'note' => $context['note'] ?? null,
            'metadata' => $context['metadata'] ?? null,
            'created_by' => $context['actor_id'] ?? Auth::id(), // ใครเป็นคนทำ event นี้
        ]);
    }

    // อัปเดตสถานะว่าตอนนี้ ticket breach SLA แล้วหรือยัง
    public function refreshSlaBreachState(Ticket $ticket): void
    {
        // ถ้า ticket นี้ไม่ได้ตั้ง sla_due_at ก็ไม่ต้องเช็ค
        if (!$ticket->sla_due_at) {
            return;
        }

        // breach = เกินกำหนดเวลา SLA แล้ว และยังไม่ถูก resolved
        $isBreached = now()->greaterThan($ticket->sla_due_at) && !$ticket->resolved_at;

        // ถ้าค่าใหม่ไม่เท่ากับของเดิม ค่อย save เพื่อลดการเขียน DB เกินจำเป็น
        if ($isBreached !== $ticket->is_sla_breached) {
            $ticket->forceFill(['is_sla_breached' => $isBreached])->save();
        }
    }

    // mark ว่า ticket นี้ถูกตอบครั้งแรกแล้ว (set first_response_at ครั้งเดียว)
    public function markResponded(Ticket $ticket): void
    {
        if (!$ticket->first_response_at) {
            $ticket->forceFill(['first_response_at' => now()])->save();
        }
    }

    // ตั้งเวลา resolved_at / closed_at ตามสถานะที่เปลี่ยน
    public function applyResolutionDates(Ticket $ticket, string $status): void
    {
        // ถ้าเปลี่ยนเป็น resolved หรือ closed และยังไม่เคย set resolved_at ให้ set ตอนนี้
        if (in_array($status, ['resolved', 'closed']) && !$ticket->resolved_at) {
            $ticket->forceFill(['resolved_at' => now()])->save();
        }

        // ถ้าเปลี่ยนเป็น closed และยังไม่เคย set closed_at ให้ set ตอนนี้
        if ($status === 'closed' && !$ticket->closed_at) {
            $ticket->forceFill(['closed_at' => now()])->save();
        }
    }

    // (ฟังก์ชันภายใน) เสนอ user id ของคนที่ควรจะถูก assign ticket ให้ (fallback ง่าย ๆ)
    protected function suggestAssigneeId(?string $assignmentGroup): ?int
    {
        // เลือก staff ที่ active คนแรก ๆ จากระบบ
        $staff = User::where('role', User::ROLE_STAFF)->where('is_active', true)->orderBy('id')->first();

        if ($staff) {
            return $staff->id;
        }

        // ถ้าไม่มี staff ให้ลองหา admin ที่ active แทน
        $admin = User::where('role', User::ROLE_ADMIN)->where('is_active', true)->orderBy('id')->first();

        return $admin?->id;
    }
}
