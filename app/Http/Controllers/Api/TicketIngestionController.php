<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketIngestionRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketEventNotification;
use App\Services\KnowledgeBase\KnowledgeBaseService;
use App\Services\Ticket\TicketAutomationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class TicketIngestionController extends Controller
{
    // ใช้ dependency injection เอา service ต่าง ๆ เข้ามาใช้ใน controller นี้
    public function __construct(
        protected TicketAutomationService $automation,      // service สำหรับ logic ออโต้ของ ticket (เช่น enrich ข้อมูล, log event)
        protected KnowledgeBaseService $knowledgeBase      // service สำหรับดึงคำแนะนำจาก knowledge base
    ) {
    }

    // endpoint สำหรับรับ ticket จากช่องทางภายนอก (ingestion) เช่น chatbot, email, API อื่น ๆ
    public function store(TicketIngestionRequest $request)
    {
        // ข้อมูลที่ผ่านการ validate แล้วจาก TicketIngestionRequest
        $validated = $request->validated();

        // หาว่า "requester" คือใคร (ผู้ร้องขอ/ผู้สร้าง ticket)
        $requester = $this->resolveRequester($validated);

        // รวม payload เดิมกับข้อมูลเพิ่ม เช่น created_by, assigned_to
        // แล้วส่งให้ automation service enrichPayload เพื่อเติมค่าต่าง ๆ ให้ครบ
        $payload = $this->automation->enrichPayload(array_merge($validated, [
            'created_by' => $requester->id,
            'assigned_to' => null,
        ]));

        // สร้าง ticket ใหม่ในฐานข้อมูลจาก payload ที่ enrich แล้ว
        $ticket = Ticket::create($payload);

        // บันทึก event ว่า ticket ถูก ingested มาจาก channel ไหน
        $this->automation->logEvent($ticket, 'ingested', [
            'note' => 'Ticket ingested via ' . ucfirst($ticket->channel),
            'actor_id' => $requester->id,
            'metadata' => [
                'channel' => $ticket->channel,
                'requester_contact' => $validated['requester_contact'] ?? null,
                'ingestion_reference' => $validated['ingestion_reference'] ?? null,
                'metadata' => $validated['metadata'] ?? [],
            ],
        ]);

        // ดึง admin ทั้งหมดมา (เพื่อใช้แจ้งเตือน)
        $admins = User::where('role', 'admin')->get();
        if ($admins->isNotEmpty()) {
            // ส่ง Notification แจ้ง admin ว่ามี ticket ใหม่ที่ถูก ingest เข้ามา
            Notification::send(
                $admins,
                new TicketEventNotification(
                    $ticket,
                    'New ticket ingested via ' . ucfirst($ticket->channel),
                    ['event_type' => 'ingested']
                )
            );
        }

        // ขอคำแนะนำ/บทความจาก knowledge base ที่เกี่ยวข้องกับ ticket นี้ (ใช้ช่วยแก้ปัญหาเร็วขึ้น)
        $suggestions = $this->knowledgeBase->suggestForTicket($ticket);

        // ตอบกลับเป็น JSON พร้อมข้อมูลสำคัญของ ticket ที่สร้างเสร็จแล้ว และ suggestion จาก KB
        return response()->json([
            'ticket_id' => $ticket->id,
            'status' => $ticket->status,
            'assignment_group' => $ticket->assignment_group,
            'priority' => $ticket->priority,
            'sla_due_at' => $ticket->sla_due_at,
            'knowledge_base_suggestions' => $suggestions,
        ], 201); // 201 = Created
    }

    // หาว่าผู้ร้องขอ (requester) จะ map เป็น user คนไหนในระบบ
    protected function resolveRequester(array $validated): User
    {
        // ถ้ามี user login อยู่ (เรียก API แบบ authenticated) ก็ใช้ user ปัจจุบันเป็น requester
        if ($user = auth()->user()) {
            return $user;
        }

        // ถ้าไม่มี login แต่มีส่ง requester_email มา
        if (!empty($validated['requester_email'])) {
            // หา user จาก email ถ้าไม่มีให้สร้างใหม่ (guest/user จากภายนอก)
            return User::firstOrCreate(
                ['email' => $validated['requester_email']],
                [
                    'name' => $validated['requester_name'] ?? $validated['requester_email'],
                    'password' => Hash::make(Str::random(24)),
                    'role' => 'user',
                ]
            );
        }

        // ถ้าไม่มีทั้ง user login และไม่มี requester_email ให้ถือว่า ticket นี้มาจากระบบ/ช่องทางที่ผูกกับ admin
        // เลยใช้ admin คนแรกในระบบเป็น requester แทน (กัน error)
        return User::where('role', 'admin')->firstOrFail();
    }
}
