<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketEventNotification;
use App\Services\KnowledgeBase\KnowledgeBaseService;
use App\Services\Ticket\TicketAutomationService;
use App\Support\Concerns\NotifiesTicketStakeholders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    // ใช้ trait สำหรับช่วยแจ้งเตือนผู้ที่เกี่ยวข้องกับ ticket (creator, assignee, admin ฯลฯ)
    use NotifiesTicketStakeholders;

    public function __construct(
        protected TicketAutomationService $automation,     // service รวม logic อัตโนมัติของ ticket (SLA, log, assign ฯลฯ)
        protected KnowledgeBaseService $knowledgeBase     // service สำหรับดึงข้อมูลจาก knowledge base
    ) {
        // ให้ Laravel ผูก policy แบบ resource กับ Ticket
        // ทำให้ method index/show/create/store/edit/update/destroy เช็คสิทธิ์อัตโนมัติ (ผ่าน TicketPolicy)
        $this->authorizeResource(Ticket::class, 'ticket');
    }

    // แสดงรายการ ticket ของผู้ใช้ปัจจุบัน (หรือ ticket ที่ assign ให้ agent)
    public function index(Request $request)
    {
        $user = Auth::user();
        $statusKeys = array_keys($this->statusOptions());
        $channels = array_keys(config('ticketing.channels', []));
        $priorities = ['low', 'normal', 'high'];

        // เก็บค่าตัวกรองจาก query string (status, channel, priority)
        $filters = [
            'status' => $request->query('status'),
            'channel' => $request->query('channel'),
            'priority' => $request->query('priority'),
        ];

        // ตรวจสอบ (sanitize) ค่า filter ป้องกันค่าที่ไม่อยู่ใน options
        if (!in_array($filters['status'], $statusKeys, true)) {
            $filters['status'] = null;
        }
        if (!in_array($filters['channel'], $channels, true)) {
            $filters['channel'] = null;
        }
        if (!in_array($filters['priority'], $priorities, true)) {
            $filters['priority'] = null;
        }

        // เตรียม query ดึง ticket พร้อมข้อมูลความสัมพันธ์ creator และ assignee
        $query = Ticket::with(['creator', 'assignee']);

        // ถ้าเป็น agent (staff) ที่ไม่ใช่ admin -> เห็นเฉพาะ ticket ที่ assign ให้ตัวเอง
        // ถ้าเป็น user ปกติหรือ admin -> ใช้ scope ownedBy(user) (เช่น ticket ที่ตัวเองสร้าง)
        if ($user->isAgent() && !$user->isAdmin()) {
            $query->where('assigned_to', $user->id);
        } else {
            $query->ownedBy($user);
        }

        // apply filter ต่าง ๆ + จัดเรียงล่าสุดก่อน + แบ่งหน้า
        $tickets = $query
            ->status($filters['status'])
            ->when($filters['channel'], fn ($query, $channel) => $query->where('channel', $channel))
            ->when($filters['priority'], fn ($query, $priority) => $query->where('priority', $priority))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('screens.tickets.index', [
            'tickets' => $tickets,
            'activePage' => 'my-ticket',
            // ถ้าเป็น agent (staff) แสดงข้อความ My Assigned Tickets ไม่งั้น My Tickets
            'pageTitle' => $user->isAgent() && !$user->isAdmin() ? 'My Assigned Tickets' : 'My Tickets',
            'assignedView' => $user->isAgent() && !$user->isAdmin(),
            'filters' => $filters,
            'statusOptions' => $this->statusOptions(),
            'channels' => config('ticketing.channels'),
            'statusSummary' => $this->statusSummary(),      // ใช้แสดงจำนวน ticket ตามสถานะ (summary)
            'priorityOptions' => $this->priorityLabels(),
        ]);
    }

    // แสดงฟอร์มสร้าง ticket ใหม่
    public function create()
    {
        // เตรียมข้อมูล categories, channels, impacts, KB และ priority ให้ฟอร์ม
        return view('screens.tickets.create', [
            'activePage' => 'create-ticket',
            'categories' => $this->categories(),
            'channels' => config('ticketing.channels'),
            'impacts' => config('ticketing.impacts'),
            // ดึงบทความจาก knowledge base มาแนะนำ 3 อันแรก
            'knowledgeBaseArticles' => collect($this->knowledgeBase->all())->take(3),
            'priorityOptions' => $this->priorityLabels(),
        ]);
    }

    // บันทึก ticket ใหม่จากฟอร์ม
    public function store(Request $request)
    {
        // เตรียม options สำหรับตรวจสอบค่าที่กรอกเข้ามา
        $channels = array_keys(config('ticketing.channels', []));
        $priorityOptions = ['low', 'normal', 'high'];

        // กำหนด validation rule
        $rules = [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['nullable', Rule::in($priorityOptions)],
            'channel' => ['nullable', Rule::in($channels)],
            'attachment' => ['nullable', 'file', 'max:20480'], // แนบไฟล์ได้สูงสุด ~20MB
        ];

        // ทุกคนสามารถระบุ category ได้ (ใช้แสดงในหน้ารายละเอียด)
        $rules['category'] = ['nullable', Rule::in(array_keys($this->categories()))];

        $validated = $request->validate($rules);

        // ถ้า user ไม่ใช่ agent หรือไม่ได้กำหนด channel ให้ default เป็น 'portal'
        if (!$request->user()->isAgent() || empty($validated['channel'])) {
            $validated['channel'] = 'portal';
        }

        // จัดการไฟล์แนบ ถ้ามี -> เก็บลง storage/public/attachments
        $attachmentPath = $request->hasFile('attachment')
            ? $request->file('attachment')->store('attachments', 'public')
            : null;

        // ให้ automation service ช่วย enrich payload (เติมค่า priority, SLA, assignment ฯลฯ)
        $payload = $this->automation->enrichPayload(array_merge(
            $validated,
            [
                'created_by' => Auth::id(),
                'assigned_to' => null,
        ]));

        // ถ้ามีไฟล์แนบ ให้เก็บ path ลง payload
        if ($attachmentPath) {
            $payload['attachment_path'] = $attachmentPath;
        }

        // สร้าง ticket ในฐานข้อมูล
        $ticket = Ticket::create($payload);

        // log event ว่า ticket ถูกสร้าง พร้อม from_status/to_status
        $this->automation->logEvent($ticket, 'created', [
            'from_status' => null,
            'to_status' => $ticket->status,
            'note' => 'Ticket created via ' . ucfirst($ticket->channel),
        ]);

        // ถ้ามีการ auto-assign ให้ใครตอนสร้าง ticket ให้ log event assignment ด้วย
        if ($ticket->assigned_to) {
            $this->automation->logEvent($ticket, 'assignment', [
                'metadata' => ['to_user_id' => $ticket->assigned_to],
                'note' => 'Automatically assigned from open rules',
            ]);

            // แจ้งเตือน assignee ใหม่ว่ามี ticket ถูก assign ให้
            $this->notifyAssigneeChange($ticket, null);
        }

        // ตั้งค่ารูปแบบการแจ้งเตือน admin เมื่อมี ticket ใหม่ จาก config
        $notificationOptions = config('ticketing.notifications', []);
        $validEventTypes = ['created', 'assignment'];
        $preferredEventType = $notificationOptions['new_ticket_event'] ?? 'created';

        // ถ้าค่าที่ตั้งใน config ไม่อยู่ใน validEvents -> กลับไปใช้ created
        if (!in_array($preferredEventType, $validEventTypes, true)) {
            $preferredEventType = 'created';
        }

        // ถ้าตั้งให้แจ้งเฉพาะตอน assignment แต่ ticket ยังไม่ถูก assign -> กลับไปใช้ created
        if ($preferredEventType === 'assignment' && !$ticket->assigned_to) {
            $preferredEventType = 'created';
        }

        // preload ความสัมพันธ์ assignee ถ้าจำเป็น
        $ticket->loadMissing('assignee');

        // ข้อความที่จะส่งไปใน Notification ถึง admin
        $message = $preferredEventType === 'assignment'
            ? 'Ticket auto-assigned to ' . ($ticket->assignee?->name ?? 'an assignment group') . '.'
            : 'New ticket created via ' . ucfirst($ticket->channel) . '.';

        // รายชื่อ user id ที่ไม่ต้องการให้ได้รับการแจ้งเตือน (เช่น assignee เอง)
        $excludeRecipients = [];

        if (
            ($notificationOptions['exclude_assignee_from_new_ticket_event'] ?? false)
            && $ticket->assigned_to
        ) {
            $excludeRecipients[] = $ticket->assigned_to;
        }

        // แจ้งเตือน admin ตามเงื่อนไขที่ตั้งไว้
        $this->notifyAdminsAboutTicket($ticket, $message, $preferredEventType, $excludeRecipients);

        return redirect()->route('tickets.index')->with('status', 'Ticket created.');
    }

    // แสดงรายละเอียดของ ticket (รวม comment + status history + KB suggestion)
    public function show(Ticket $ticket)
    {
        // โหลดข้อมูลที่เกี่ยวข้อง เช่น creator, assignee, statusHistories.actor
        $ticket->load(['creator', 'assignee', 'statusHistories.actor']);

        $viewer = Auth::user();

        // ดึงคอมเมนต์ที่ user คนนี้มีสิทธิ์เห็น (เช่น public หรือ internal สำหรับ agent)
        $comments = $ticket->comments()
            ->visibleTo($viewer)
            ->with('author')
            ->latest()
            ->get();

        return view('screens.tickets.show', [
            'ticket' => $ticket,
            'comments' => $comments,
            'activePage' => 'my-ticket',
            'knowledgeBaseSuggestions' => $this->knowledgeBase->suggestForTicket($ticket),
            'priorityOptions' => $this->priorityLabels(),
            'viewer' => $viewer,
            'categories' => $this->categories(),
        ]);
    }

    // แสดงฟอร์มแก้ไข ticket
    public function edit(Ticket $ticket)
    {
        // เช็คสิทธิ์ว่า user ปัจจุบันสามารถจัดการ assignment ได้ไหม (ผ่าน policy)
        $canManageAssignments = Auth::user()->can('manageAssignments', $ticket);

        return view('screens.tickets.edit', [
            'ticket' => $ticket,
            'activePage' => 'my-ticket',
            'categories' => $this->categories(),
            'channels' => config('ticketing.channels'),
            'impacts' => config('ticketing.impacts'),
            'statusOptions' => $this->statusOptions(),
            // รายชื่อ staff สำหรับเลือก assign
            'assignees' => User::where('role', User::ROLE_STAFF)->orderBy('name')->get(),
            'assignmentGroups' => config('ticketing.assignment_groups'),
            'canManageAssignments' => $canManageAssignments,
            'priorityOptions' => $this->priorityLabels(),
        ]);
    }

    // อัปเดตข้อมูล ticket (ใช้โดย staff/agent เป็นหลัก)
    public function update(Request $request, Ticket $ticket)
    {
        // เตรียม options และ rule
        $statusKeys = array_keys($this->statusOptions());
        $channels = array_keys(config('ticketing.channels', []));
        $priorityOptions = ['low', 'normal', 'high'];
        $impactOptions = ['low', 'medium', 'high'];

        // ถ้า agent พยายามแก้ ticket ที่ปิดแล้ว (closed) ให้ห้าม
        if ($request->user()->isAgent() && $ticket->status === 'closed') {
            abort(403);
        }

        // กรณี staff/agent: อนุญาตให้แก้ status เป็นหลัก และช่องอื่น ๆ แบบ optional
        if ($request->user()->isAgent()) {
            $rules = [
                'subject' => ['sometimes', 'string', 'max:255'],
                'description' => ['sometimes', 'string'],
                'priority' => ['sometimes', Rule::in($priorityOptions)],
                'impact' => ['sometimes', Rule::in($impactOptions)],
                'category' => ['sometimes', Rule::in(array_keys($this->categories()))],
                'status' => ['required', Rule::in($statusKeys)],
                'status_note' => ['nullable', 'string'],
                'attachment' => ['nullable', 'file', 'max:20480'],
                'assigned_to' => ['nullable', 'exists:users,id'],
                'channel' => ['nullable', Rule::in($channels)],
                'assignment_group' => ['nullable', 'string', 'max:255'],
            ];
        } else {
            // fallback (กรณี non-agent) – ปกติจะไม่ถูกใช้มากเพราะมี policy กำกับ
            $rules = [
                'subject' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string'],
                'priority' => ['required', Rule::in($priorityOptions)],
                'impact' => ['nullable', Rule::in($impactOptions)],
                'category' => ['nullable', Rule::in(array_keys($this->categories()))],
                'status' => ['required', Rule::in($statusKeys)],
                'status_note' => ['nullable', 'string'],
                'attachment' => ['nullable', 'file', 'max:20480'],
            ];
        }

        $validated = $request->validate($rules);

        $originalStatus = $ticket->status;
        $originalAssignee = $ticket->assigned_to;

        // เตรียมข้อมูลที่จะอัปเดต (ถ้าไม่ได้ส่งมาให้ใช้ค่าเดิม)
        $updateData = [
            'subject' => $validated['subject'] ?? $ticket->subject,
            'description' => $validated['description'] ?? $ticket->description,
            'priority' => $validated['priority'] ?? $ticket->priority,
            'category' => $validated['category'] ?? $ticket->category,
            'status' => $validated['status'],
            'impact' => $validated['impact'] ?? $ticket->impact ?? 'medium',
        ];

        // ถ้าเป็น agent ถึงจะสามารถแก้ assigned_to, channel, assignment_group ได้
        if ($request->user()->isAgent()) {
            $updateData['assigned_to'] = $validated['assigned_to'] ?? $ticket->assigned_to;
            $updateData['channel'] = $validated['channel'] ?? $ticket->channel;
            $updateData['assignment_group'] = $validated['assignment_group'] ?? $ticket->assignment_group;
        }

        // จัดการไฟล์แนบใหม่: ถ้ามีไฟล์ใหม่ -> ลบไฟล์เก่าแล้วเก็บ path ของไฟล์ใหม่
        if ($request->hasFile('attachment')) {
            if ($ticket->attachment_path) {
                Storage::disk('public')->delete($ticket->attachment_path);
            }
            $updateData['attachment_path'] = $request->file('attachment')->store('attachments', 'public');
        }

        // อัปเดต ticket ในฐานข้อมูล
        $ticket->update($updateData);

        // เช็คว่า priority เปลี่ยนไหม ถ้าเปลี่ยนให้คำนวณ SLA window ใหม่
        $priorityChanged = $ticket->wasChanged('priority');

        if ($priorityChanged) {
            $ticket->forceFill($this->automation->determineSlaWindows($ticket->priority))->save();
        }

        // ถ้า agent เปลี่ยน assignee ให้ log event assignment และแจ้งเตือน
        if ($request->user()->isAgent() && $originalAssignee !== $ticket->assigned_to) {
            $this->automation->logEvent($ticket, 'assignment', [
                'note' => $validated['status_note'] ?? null,
                'metadata' => [
                    'from_user_id' => $originalAssignee,
                    'to_user_id' => $ticket->assigned_to,
                ],
            ]);

            $this->notifyAssigneeChange($ticket, $originalAssignee);
        }

        // ถ้าสถานะ ticket เปลี่ยน (เช่น จาก in_progress -> resolved)
        if ($originalStatus !== $ticket->status) {
            // ใช้ automation service ตั้งค่าเวลาที่เกี่ยวข้องกับการ resolve/close ตาม status
            $this->automation->applyResolutionDates($ticket, $ticket->status);

            // log event การเปลี่ยนสถานะ
            $this->automation->logEvent($ticket, 'status_change', [
                'from_status' => $originalStatus,
                'to_status' => $ticket->status,
                'note' => $validated['status_note'] ?? null,
            ]);

            // ข้อความแจ้ง stakeholders
            $message = 'Ticket status changed to ' . $ticket->status_label;
            if (!empty($validated['status_note'])) {
                $message .= ' – ' . $validated['status_note'];
            }

            // แจ้งเตือนผู้เกี่ยวข้อง (creator, assignee, admin ตาม trait)
            $this->notifyStakeholders($ticket, $message, ['event_type' => 'status_change']);

            // ส่ง Notification เฉพาะให้ creator ด้วยว่าตัว staff เปลี่ยนสถานะ ticket แล้ว
            if ($ticket->creator && $ticket->creator->isActive()) {
                Notification::send($ticket->creator, new TicketEventNotification(
                    $ticket,
                    'Your ticket status has been updated by the assigned staff.',
                    ['event_type' => 'status_change']
                ));
            }
        }

        // ถ้าเป็น agent แสดงว่าได้ตอบแล้ว ให้ mark ว่า ticket นี้มีการตอบกลับ
        if ($request->user()->isAgent()) {
            $this->automation->markResponded($ticket);
        }

        // อัปเดตสถานะว่า breach SLA แล้วหรือยัง
        $this->automation->refreshSlaBreachState($ticket);

        return redirect()->route('tickets.show', $ticket)->with('status', 'Ticket updated.');
    }

    // ให้เจ้าของ ticket กด approve ว่างานเสร็จแล้ว -> ปิด ticket
    public function approve(Ticket $ticket)
    {
        // ต้องมีสิทธิ์ view ticket ก่อน
        $this->authorize('view', $ticket);

        // เช็คว่าเป็นเจ้าของ ticket จริง ๆ หรือเปล่า
        if ($ticket->created_by !== Auth::id()) {
            abort(403);
        }

        $fromStatus = $ticket->status;
        $ticket->update(['status' => 'closed']);

        // log event ว่าเจ้าของ approve แล้ว
        $this->automation->logEvent($ticket, 'status_change', [
            'from_status' => $fromStatus,
            'to_status' => 'closed',
            'note' => 'Ticket approved by owner.',
        ]);

        // แจ้ง assignee ว่า ticket ถูกปิดโดยเจ้าของ
        if ($ticket->assignee && $ticket->assignee->isActive()) {
            Notification::send($ticket->assignee, new TicketEventNotification(
                $ticket,
                'The owner approved the resolution; ticket is now closed.',
                ['event_type' => 'status_change']
            ));
        }

        return back()->with('status', 'Ticket marked as complete.');
    }

    // ให้เจ้าของ ticket กด reject งาน -> กลับไป in_progress
    public function reject(Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        // ต้องเป็นเจ้าของ ticket เท่านั้นถึงจะ reject ได้
        if ($ticket->created_by !== Auth::id()) {
            abort(403);
        }

        $fromStatus = $ticket->status;
        $ticket->update(['status' => 'in_progress']);

        // log event ว่า owner ปฏิเสธคำตอบ
        $this->automation->logEvent($ticket, 'status_change', [
            'from_status' => $fromStatus,
            'to_status' => 'in_progress',
            'note' => 'Owner rejected the solution; ticket back in progress.',
        ]);

        // แจ้ง assignee ว่าถูกส่งกลับมาแก้ต่อ
        if ($ticket->assignee && $ticket->assignee->isActive()) {
            Notification::send($ticket->assignee, new TicketEventNotification(
                $ticket,
                'Ticket rejected by owner and returned to you for follow-up.',
                ['event_type' => 'status_change']
            ));
        }

        return back()->with('status', 'Ticket has been marked as in progress.');
    }

    // ลบ ticket (รวมถึงไฟล์แนบถ้ามี)
    public function destroy(Ticket $ticket)
    {
        // ถ้ามีไฟล์แนบ ให้ลบออกจาก storage ก่อน
        if ($ticket->attachment_path) {
            Storage::disk('public')->delete($ticket->attachment_path);
        }

        $ticket->delete();

        return redirect()->route('tickets.index')->with('status', 'Ticket deleted.');
    }

    // ดึงรายการสถานะทั้งหมดจาก config
    protected function statusOptions(): array
    {
        return config('ticketing.statuses', []);
    }

    // สร้าง summary จำนวน ticket แยกตาม status สำหรับ user ปัจจุบัน
    protected function statusSummary(): array
    {
        $user = Auth::user();

        $base = Ticket::query();
        if ($user->isAgent() && !$user->isAdmin()) {
            $base->where('assigned_to', $user->id);
        } else {
            $base->ownedBy($user);
        }

        return collect($this->statusOptions())
            ->map(function (array $definition, string $status) use ($base) {
                $count = (clone $base)->where('status', $status)->count();

                return [
                    'status' => $status,
                    'label' => $definition['label'],
                    'color' => $definition['color'],
                    'count' => $count,
                ];
            })
            ->values()
            ->all();
    }

    // label สำหรับ priority แต่ละระดับ
    private function priorityLabels(): array
    {
        return [
            'low' => 'Low',
            'normal' => 'Medium',
            'high' => 'High',
        ];
    }

    // รายการหมวดหมู่ ticket (hard-coded)
    protected function categories(): array
    {
        return [
            'network' => 'Network',
            'hardware' => 'Hardware',
            'software' => 'Software',
            'access' => 'Access / Account',
            'other' => 'Other',
        ];
    }

    // แจ้งเตือน assignee เมื่อมีการเปลี่ยนผู้รับผิดชอบ
    protected function notifyAssigneeChange(Ticket $ticket, ?int $previousAssigneeId): void
    {
        $message = 'You have been assigned a ticket.';

        if ($ticket->assignee && $ticket->assignee->isActive()) {
            Notification::send(
                $ticket->assignee,
                new TicketEventNotification($ticket, $message, ['event_type' => 'assignment'])
            );
        }
    }

    // แจ้งเตือน admin เมื่อมี ticket ใหม่/ingested ฯลฯ
    protected function notifyAdminsAboutTicket(Ticket $ticket, string $message, string $eventType = 'created', array $excludeUserIds = []): void
    {
        // เลือกเฉพาะ admin ที่ active และไม่ใช่คนที่สร้าง ticket นี้
        $recipientsQuery = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->where('is_active', true)
            ->where('id', '!=', Auth::id())
            ->when(
                !empty($excludeUserIds),
                fn ($query) => $query->whereNotIn('id', $excludeUserIds)
            );

        $recipients = $recipientsQuery->get();

        if ($recipients->isNotEmpty()) {
            Notification::send(
                $recipients,
                new TicketEventNotification($ticket, $message, ['event_type' => $eventType])
            );
        }
    }

}
