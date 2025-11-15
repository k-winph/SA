@php
    // ใช้ Storage facade สำหรับสร้าง URL ของไฟล์แนบ
    use Illuminate\Support\Facades\Storage;
@endphp

@extends('layouts.app') {{-- ใช้ layout หลัก --}}

@section('title', 'Ticket Detail') {{-- ตั้ง title หน้าเป็น Ticket Detail --}}

@section('content')
    @php
        // เช็คว่าเป็นมุมมองของ Staff (Agent แต่ไม่ใช่ Admin)
        $isStaffView = auth()->user()?->isAgent() && !auth()->user()?->isAdmin();

        // แปลง id ticket ให้เป็นรูปแบบ TKT-0001
        $ticketCode = 'TKT-' . str_pad((string) $ticket->id, 4, '0', STR_PAD_LEFT);

        // เช็คว่า ticket ปิดแล้วหรือยัง
        $isClosedTicket = $ticket->status === 'closed';

        // แปลง priority เป็น label ที่อ่านง่าย ถ้าไม่มีใน options ใช้ ucfirst()
        $priorityLabel = $priorityOptions[$ticket->priority] ?? ucfirst($ticket->priority);

        // แปลง category ให้เป็น label จาก $categories หรือแปลงชื่อธรรมดา ถ้าไม่มีให้เป็น '—'
        $categoryLabel = $ticket->category
            ? ($categories[$ticket->category] ?? ucfirst(str_replace('_', ' ', $ticket->category)))
            : '—';

        // ถ้ามีไฟล์แนบ ตัดเอาแค่ชื่อไฟล์ ไม่เอา path ทั้งหมด
        $attachmentName = $ticket->attachment_path ? basename($ticket->attachment_path) : '—';

        // ถ้ามีไฟล์แนบ สร้าง URL สำหรับกดดาวน์โหลด/เปิดดู
        $attachmentUrl = $ticket->attachment_path ? Storage::url($ticket->attachment_path) : null;
    @endphp

    {{-- ส่วนหัวของหน้า แสดงชื่อหน้าแตกต่างกันถ้าเป็น staff view --}}
    <div class="page-header">
        <h1 class="page-title">
            {{ $isStaffView ? 'My Assigned Tickets' : 'Ticket #' . $ticket->id }}
        </h1>

        {{-- ปุ่ม Edit Ticket จะโชว์ก็ต่อเมื่อ user มีสิทธิ์ update ticket นี้ --}}
        @can('update', $ticket)
            <a class="btn btn-primary" href="{{ route('tickets.edit', $ticket) }}">Edit Ticket</a>
        @endcan
    </div>

    <section class="card">
        {{-- ปุ่ม tab ด้านบน ใช้สลับระหว่าง Detail / Timeline / Conversation --}}
        <div class="tab-buttons" data-tabs>
            <button type="button" class="tab-btn active" data-target="detail">Detail</button>
            <button type="button" class="tab-btn" data-target="timeline">Timeline</button>
            <button type="button" class="tab-btn" data-target="conversation">Conversation</button>
        </div>

        {{-- TAB: รายละเอียด Ticket --}}
        <div class="tab-panel active" id="detail-panel">
            <div class="ticket-detail-card">
                <div class="ticket-detail-row">
                    <span class="ticket-detail-label">Ticket ID :</span>
                    <span class="ticket-detail-value">{{ $ticketCode }}</span>
                </div>
                <div class="ticket-detail-row">
                    <span class="ticket-detail-label">Subject :</span>
                    <span class="ticket-detail-value">{{ $ticket->subject }}</span>
                </div>
                <div class="ticket-detail-row">
                    <span class="ticket-detail-label">Description :</span>
                    <span class="ticket-detail-value">{{ $ticket->description }}</span>
                </div>
                <div class="ticket-detail-row">
                    <span class="ticket-detail-label">Priority :</span>
                    <span class="ticket-detail-value">{{ $priorityLabel }}</span>
                </div>
                <div class="ticket-detail-row">
                    <span class="ticket-detail-label">Category :</span>
                    <span class="ticket-detail-value">{{ $categoryLabel }}</span>
                </div>
                <div class="ticket-detail-row">
                    <span class="ticket-detail-label">Attachment :</span>
                    <span class="ticket-detail-value">
                        {{-- ถ้ามีไฟล์แนบ ให้แสดงลิงก์เพื่อเปิด/ดาวน์โหลด ไม่งั้นแสดงขีด — --}}
                        @if ($attachmentUrl)
                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noreferrer">
                                {{ $attachmentName }}
                            </a>
                        @else
                            —
                        @endif
                    </span>
                </div>
            </div>

            {{-- ปุ่มให้ผู้สร้าง ticket กด Approve/Reject ได้ ถ้า ticket ยังไม่ถูกปิด --}}
            @if ($viewer && $viewer->id === $ticket->created_by && $ticket->status !== 'closed')
                <div class="ticket-actions user-approval-actions">
                    {{-- ปุ่ม Reject --}}
                    <form action="{{ route('tickets.reject', $ticket) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-cancel">Reject</button>
                    </form>

                    {{-- ปุ่ม Approve --}}
                    <form action="{{ route('tickets.approve', $ticket) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-submit">Approve</button>
                    </form>
                </div>
            @endif
        </div>

        {{-- TAB: Timeline ของเหตุการณ์ต่าง ๆ ของ ticket --}}
        <div class="tab-panel" id="timeline-panel">
            <div class="timeline">
                @php
                    // ดึง statusHistories มาจัดเรียงตามเวลาที่สร้าง (จากเก่าไปใหม่)
                    $timeline = $ticket->statusHistories->sortBy('created_at');
                @endphp

                {{-- วนลูปสร้างรายการ timeline ถ้าไม่มีเลยจะไปเข้า @empty --}}
                @forelse ($timeline as $history)
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            @php
                                // แปลง timezone ตามที่ config กำหนด และจัดรูปแบบวันที่เวลา
                                $timelineTime = $history->created_at->timezone(config('app.timezone'));
                            @endphp
                            <p class="timeline-title">
                                {{-- แสดงชื่อ event_type เช่น status change, created ฯลฯ --}}
                                <strong>{{ ucfirst(str_replace('_', ' ', $history->event_type)) }}</strong>
                                <span>&middot; {{ $timelineTime->format('d M Y H:i') }}</span>
                            </p>

                            {{-- คำอธิบายรายละเอียด event ตามประเภทเหตุการณ์ --}}
                            <p class="timeline-body">
                                @switch($history->event_type)
                                    @case('status_change')
                                        {{ $history->actor?->name ?? 'System' }} moved the ticket from
                                        <em>{{ $history->from_status ?? 'N/A' }}</em>
                                        to
                                        <em>{{ $history->to_status }}</em>.
                                        @break

                                    @case('assignment')
                                        {{ $history->actor?->name ?? 'System' }} updated the assignment.
                                        @break

                                    @case('comment')
                                        {{ $history->actor?->name ?? 'System' }} added a
                                        {{ $history->metadata['visibility'] ?? 'public' }} comment.
                                        @break

                                    @case('ingested')
                                    @case('created')
                                        Open event recorded by {{ $history->actor?->name ?? 'System' }}.
                                        @break

                                    @default
                                        {{ $history->actor?->name ?? 'System' }} logged an event.
                                @endswitch
                            </p>

                            {{-- ถ้ามี note เพิ่มเติมจะแสดงใต้ event --}}
                            @if ($history->note)
                                <p class="timeline-note">Note: {{ $history->note }}</p>
                            @endif
                        </div>
                    </div>
                @empty
                    {{-- ถ้า ticket ยังไม่มีประวัติอะไรเลย --}}
                    <p>No status history yet.</p>
                @endforelse
            </div>
        </div>

        {{-- TAB: ส่วนสนทนา/คอมเมนต์ของ ticket --}}
        <div class="tab-panel" id="conversation-panel">
            <div class="conversation-log">
                {{-- แสดงคอมเมนต์ทั้งหมดของ ticket --}}
                @forelse ($comments as $comment)
                    @php
                        // เช็คว่าคอมเมนต์นี้เป็นของ user ปัจจุบันไหม เพื่อให้สิทธิ์แก้ไขเฉพาะเจ้าของคอมเมนต์
                        $canEditComment = auth()->id() === $comment->user_id;
                    @endphp

                    <div class="conversation-entry" id="comment-{{ $comment->id }}">
                        <div class="conversation-entry-header"
                             style="display:flex;justify-content:space-between;align-items:center;gap:1rem;">
                            {{-- ชื่อคนที่คอมเมนต์ --}}
                            <strong>
                                {{ $comment->author?->name }}
                            </strong>

                            {{-- ปุ่ม Edit comment แสดงเฉพาะเจ้าของคอมเมนต์ และถ้า ticket ยังไม่ถูกปิด --}}
                            @if ($canEditComment && !$isClosedTicket)
                                <button class="btn ghost btn-xs"
                                        type="button"
                                        data-comment-edit="{{ $comment->id }}">
                                    Edit
                                </button>
                            @endif
                        </div>

                        {{-- เนื้อความคอมเมนต์ --}}
                        <p style="margin:0.35rem 0 0;">{{ $comment->body }}</p>

                        {{-- ฟอร์มแก้ไขคอมเมนต์ (ซ่อนอยู่ก่อน กด Edit แล้วค่อยโชว์ ด้วย JS ด้านล่าง) --}}
                        @if ($canEditComment && !$isClosedTicket)
                            <form class="comment-edit-form"
                                  id="comment-edit-{{ $comment->id }}"
                                  action="{{ route('tickets.comments.update', [$ticket, $comment]) }}"
                                  method="POST"
                                  hidden>
                                @csrf
                                @method('PUT')
                                <div class="form-group" style="margin-top:0.75rem;">
                                    <label for="edit-comment-body-{{ $comment->id }}" class="sr-only">
                                        Update comment
                                    </label>
                                    <textarea id="edit-comment-body-{{ $comment->id }}"
                                              name="body">{{ old("comment_{$comment->id}_body", $comment->body) }}</textarea>
                                </div>

                                <div class="form-actions" style="gap:0.5rem;">
                                    <button class="btn btn-submit" type="submit">Save</button>
                                    <button class="btn ghost" type="button"
                                            data-comment-cancel="{{ $comment->id }}">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                @empty
                    {{-- ถ้ายังไม่มีคอมเมนต์เลย --}}
                    <p>No comments yet.</p>
                @endforelse
            </div>

            {{-- ถ้า ticket ถูกปิดแล้ว ไม่ให้คอมเมนต์เพิ่ม --}}
            @if ($isClosedTicket)
                <p class="text-muted" style="margin-top:1.5rem;">
                    Ticket is closed, so commenting has been locked.
                </p>

            {{-- ถ้า user ปัจจุบันมีสิทธิ์ comment ใน ticket นี้ แสดงฟอร์มเพิ่มคอมเมนต์ --}}
            @elseif (auth()->user()?->can('comment', $ticket))
                <form class="comment-form"
                      action="{{ route('tickets.comments.store', $ticket) }}"
                      method="POST"
                      style="margin-top:1rem;">
                    @csrf
                    <div class="form-group">
                        <label for="comment-body">Add Comment</label>
                        <textarea id="comment-body"
                                  name="body"
                                  placeholder="Write update or note...">{{ old('body') }}</textarea>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-submit" type="submit">Post Comment</button>
                    </div>
                </form>
            @else
                {{-- ถ้าไม่มีสิทธิ์คอมเมนต์ (เช่น end-user ที่ไม่ใช่เจ้าของ ฯลฯ) --}}
                <p class="text-muted" style="margin-top:1.5rem;">
                    Comments are managed by the assigned support staff. You can monitor updates in the timeline above.
                </p>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        // ----- จัดการการเปลี่ยนแท็บ Detail / Timeline / Conversation -----
        document.querySelectorAll('.tab-buttons').forEach(group => {
            const buttons = group.querySelectorAll('.tab-btn');
            const panels = group.parentElement.querySelectorAll('.tab-panel');

            buttons.forEach(button => {
                button.addEventListener('click', () => {
                    // ลบ active จากทุกปุ่ม และทุก panel ก่อน
                    buttons.forEach(b => b.classList.remove('active'));
                    panels.forEach(panel => panel.classList.remove('active'));

                    // ใส่ active ให้ปุ่มที่ถูกคลิก
                    button.classList.add('active');

                    // เปิด panel ที่มี id ตรงกับ data-target ของปุ่ม
                    const target = group.parentElement.querySelector(`#${button.dataset.target}-panel`);
                    if (target) {
                        target.classList.add('active');
                    }
                });
            });
        });

        // ----- ปุ่ม Edit comment: toggle ฟอร์มแก้ไขคอมเมนต์ -----
        document.querySelectorAll('[data-comment-edit]').forEach(button => {
            const commentId = button.dataset.commentEdit;
            const form = document.getElementById(`comment-edit-${commentId}`);
            if (!form) {
                return;
            }

            button.addEventListener('click', () => {
                // ซ่อน/แสดงฟอร์มแก้ไข
                form.hidden = !form.hidden;

                // ถ้าเพิ่งเปิดฟอร์ม ให้โฟกัสไปที่ textarea
                if (!form.hidden) {
                    const textarea = form.querySelector('textarea');
                    textarea?.focus();
                }
            });
        });

        // ----- ปุ่ม Cancel ในฟอร์มแก้ไขคอมเมนต์ -----
        document.querySelectorAll('[data-comment-cancel]').forEach(button => {
            const commentId = button.dataset.commentCancel;
            const form = document.getElementById(`comment-edit-${commentId}`);
            if (!form) {
                return;
            }

            button.addEventListener('click', () => {
                // กด Cancel แล้วซ่อนฟอร์มแก้ไขกลับไป
                form.hidden = true;
            });
        });
    </script>
@endpush
