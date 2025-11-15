@php
    // ใช้ Storage facade สำหรับสร้าง URL ไฟล์แนบ
    use Illuminate\Support\Facades\Storage;
@endphp

@extends('layouts.app') {{-- ใช้ layout หลักของระบบ --}}

@section('title', 'Edit Ticket') {{-- ตั้ง title หน้าเป็น Edit Ticket --}}

@section('content')
    @php
        // เช็คว่าผู้ใช้ปัจจุบันเป็น Staff/Agent (แต่ไม่ใช่ Admin) ไหม
        // ถ้าใช่ จะใช้หน้าจออัปเดตแบบ staff view (เปลี่ยนสถานะอย่างเดียว)
        $isStaffView = auth()->user()?->isAgent() && !auth()->user()?->isAdmin();
    @endphp

    <div class="page-header">
        {{-- ถ้าเป็น staff view แสดงข้อความ My Assigned Tickets ถ้าไม่ใช่แสดง Edit Ticket #id --}}
        <h1 class="page-title">{{ $isStaffView ? 'My Assigned Tickets' : 'Edit Ticket #' . $ticket->id }}</h1>
    </div>

    {{-- ถ้าเป็นมุมมอง Staff (Agent) --}}
    @if ($isStaffView)
        @php
            // เช็คว่า ticket นี้ปิดแล้วหรือยัง
            $isClosedTicket = $ticket->status === 'closed';
        @endphp

        {{-- ฝั่ง staff มีการ push CSS เฉพาะหน้านี้เข้า stack 'styles' --}}
        @push('styles')
            <style>
                .center-wrap { display: flex; justify-content: center; }
                .update-panel { border: 2px solid var(--blue); border-radius: 16px; padding: 1.2rem; background: #f8fafc; max-width: 560px; margin: 0 auto; }
                .update-title { text-align: center; font-weight: 800; margin: .2rem 0 .8rem; }
                .update-subtitle { text-align: center; margin: 0 0 1rem; font-weight: 800; }
                .action-row { display: grid; grid-template-columns: 1fr 1fr; gap: .8rem; }
                .btn-cancel { background: #fecaca; color: #7f1d1d; }
                .btn-submit { background: #34d399; color: #064e3b; }
                /* พื้นหลัง overlay ตอนกำลังบันทึก (saving…) */
                .progress-overlay { position: fixed; inset: 0; background: rgba(15,23,42,.45); display: none; align-items: center; justify-content: center; z-index: 1200; }
                .progress-overlay.show { display: flex; }
                .progress-box { background: #fff; padding: 1rem 1.25rem; border-radius: 14px; box-shadow: var(--shadow); width: min(360px, 90vw); text-align: center; }
                .spinner { width: 22px; height: 22px; border: 3px solid #e5e7eb; border-top-color: var(--blue); border-radius: 50%; margin: 0 auto .6rem; animation: spin 1s linear infinite; }
                @keyframes spin { to { transform: rotate(360deg); } }
                .step { font-weight: 700; }
                .step-sub { color: var(--text-muted); font-size: .9rem; }
                /* select มี icon ลูกศร */
                .select-wrap { position: relative; }
                .select-wrap select { width: 100%; appearance: none; }
                .select-caret { position: absolute; right: .6rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-muted); }
                .select-caret svg { width: 18px; height: 18px; }
            </style>
        @endpush

        {{-- ถ้า ticket ปิดแล้ว staff จะไม่สามารถอัปเดตอะไรได้ --}}
        @if ($isClosedTicket)
            <section class="card form-card">
                <div class="card-body">
                    <h2 class="update-title">Ticket Closed</h2>
                    <p class="text-muted">
                        The owner approved the ticket, so its status is now closed and staff cannot update it anymore.
                    </p>
                    <div class="form-actions">
                        <a class="btn ghost" href="{{ route('tickets.show', $ticket) }}">Back to ticket</a>
                    </div>
                </div>
            </section>
        @else
            {{-- ถ้า ticket ยังไม่ปิด แสดงฟอร์มให้อัปเดตสถานะแบบง่าย ๆ --}}
            <div class="center-wrap">
                <section class="card" style="max-width: 720px; width: 100%;">
                    <div class="update-panel">
                        <h2 class="update-title">Update Ticket</h2>
                        {{-- แสดงรหัส Ticket ในรูปแบบ TKT-0001 --}}
                        <div class="update-subtitle">
                            TKT-{{ str_pad((string) $ticket->id, 4, '0', STR_PAD_LEFT) }}
                        </div>

                        {{-- ฟอร์มอัปเดตสถานะ ticket สำหรับ staff --}}
                        <form action="{{ route('tickets.update', $ticket) }}" method="post">
                            @csrf
                            @method('PUT')

                            <div class="form-group">
                                <label for="status">New Status</label>
                                <div class="select-wrap">
                                    {{-- dropdown เลือกสถานะใหม่ที่ staff เปลี่ยนได้ --}}
                                    <select id="status" name="status">
                                        @foreach (['in_progress' => 'In Progress', 'resolved' => 'Resolved', 'waiting' => 'On Hold'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('status', $ticket->status) === $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    {{-- icon ลูกศรของ select (ตกแต่ง UI) --}}
                                    <span class="select-caret" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd"
                                                  d="M5.23 7.21a.75.75 0 011.06.02L10 11.188l3.71-3.958a.75.75 0 111.08 1.04l-4.24 4.52a.75.75 0 01-1.08 0l-4.24-4.52a.75.75 0 01.02-1.06z"
                                                  clip-rule="evenodd"/>
                                        </svg>
                                    </span>
                                </div>
                            </div>

                            <div class="action-row">
                                {{-- ปุ่มกลับไปหน้า show ticket --}}
                                <a class="btn btn-cancel" href="{{ route('tickets.show', $ticket) }}">Cancel</a>
                                {{-- ปุ่ม submit ส่งฟอร์มอัปเดตสถานะ --}}
                                <button class="btn btn-submit" type="submit" id="submit-update">Submit</button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>

            {{-- overlay แสดงสถานะการบันทึก (Saving...) ตอนกด submit --}}
            <div id="progress-overlay" class="progress-overlay" aria-hidden="true">
                <div class="progress-box">
                    <div class="spinner"></div>
                    <div class="step" id="progress-step">Preparing...</div>
                    <div class="step-sub" id="progress-sub"></div>
                </div>
            </div>

            @push('scripts')
                <script>
                    (function () {
                        // เลือกฟอร์มของ ticket ปัจจุบันจาก action ที่ลงท้ายด้วย /tickets/{id}
                        const form = document.querySelector('form[action$="/tickets/{{ $ticket->id }}"]');
                        const overlay = document.getElementById('progress-overlay');
                        const step = document.getElementById('progress-step');
                        const sub = document.getElementById('progress-sub');

                        if (!form) return;

                        // ตอน submit ฟอร์ม แสดง overlay และเปลี่ยนข้อความ step
                        form.addEventListener('submit', function () {
                            overlay.classList.add('show');
                            overlay.removeAttribute('aria-hidden');
                            step.textContent = 'Saving...';
                            sub.textContent = 'Updating ticket status';
                            // เปลี่ยนข้อความอีกทีหลังจากผ่านไป 0.9 วินาที
                            setTimeout(() => {
                                step.textContent = 'Finishing...';
                                sub.textContent = 'Almost done';
                            }, 900);
                        });
                        // comment: เคยมีโค้ดควบคุม icon ด้านซ้ายของ select แต่ถูกลบออกแล้ว
                    })();
                </script>
            @endpush
        @endif

    {{-- ถ้าไม่ใช่ staff view = เป็น Admin หรือเจ้าของ ticket (ใช้ฟอร์มเต็ม) --}}
    @else
        <section class="card form-card">
            {{-- ฟอร์มแก้ไข ticket แบบเต็ม (subject, description, priority, assignee ฯลฯ) --}}
            <form action="{{ route('tickets.update', $ticket) }}" method="post" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                {{-- หัวข้อปัญหา --}}
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input
                        id="subject"
                        name="subject"
                        type="text"
                        value="{{ old('subject', $ticket->subject) }}"
                    >
                </div>

                {{-- รายละเอียดปัญหา --}}
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea
                        id="description"
                        name="description"
                    >{{ old('description', $ticket->description) }}</textarea>
                </div>

                {{-- ผลกระทบ (Impact) เช่น low/medium/high --}}
                <div class="form-group">
                    <label for="impact">Impact</label>
                    <select id="impact" name="impact">
                        @foreach ($impacts as $value => $label)
                            <option value="{{ $value }}" @selected(old('impact', $ticket->impact ?? 'medium') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid-two">
                    {{-- Priority ทางธุรกิจ --}}
                    <div class="form-group">
                        <label for="priority">Business Priority</label>
                        <select id="priority" name="priority">
                            @foreach ($priorityOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority', $ticket->priority) === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- ช่องทางที่ ticket เข้ามา (channel) --}}
                    <div class="form-group">
                        <label for="channel">Intake Channel</label>
                        @if ($canManageAssignments)
                            {{-- ถ้าผู้ใช้มีสิทธิ์จัดการ assignment ให้แก้ channel ได้ --}}
                            <select id="channel" name="channel">
                                @foreach ($channels as $value => $label)
                                    <option value="{{ $value }}" @selected(old('channel', $ticket->channel) === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            {{-- ถ้าไม่มีสิทธิ์จัดการ assignment ซ่อนค่าเดิมไว้ และแสดงเป็น text แต่อ่านได้อย่างเดียว --}}
                            <input type="hidden" name="channel" value="{{ $ticket->channel }}">
                            <input
                                id="channel"
                                type="text"
                                value="{{ $channels[$ticket->channel] ?? ucfirst($ticket->channel) }}"
                                disabled
                            >
                        @endif
                    </div>
                </div>

                <div class="grid-two">
                    {{-- หมวดหมู่ของ ticket --}}
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <option value="">Select Category</option>
                            @foreach ($categories as $value => $label)
                                <option value="{{ $value }}" @selected(old('category', $ticket->category) === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- กลุ่มที่รับผิดชอบ (Assignment Group) --}}
                    <div class="form-group">
                        <label for="assignment_group">Assignment Group</label>
                        @if ($canManageAssignments)
                            {{-- admin/คนที่มีสิทธิ์สามารถเปลี่ยน group ได้ --}}
                            <select id="assignment_group" name="assignment_group">
                                @foreach ($assignmentGroups as $value => $label)
                                    {{-- ตรงนี้ใช้ label เป็น value ใน DB --}}
                                    <option value="{{ $label }}" @selected(old('assignment_group', $ticket->assignment_group) === $label)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            {{-- ถ้าไม่มีสิทธิ์ แสดงค่าแบบอ่านอย่างเดียว --}}
                            <input
                                type="text"
                                value="{{ $ticket->assignment_group ?? 'Not assigned' }}"
                                disabled
                            >
                        @endif
                    </div>
                </div>

                {{-- ฟิลด์ Assigned To กำหนดว่า ticket นี้ให้ staff คนไหน --}}
                @if ($canManageAssignments)
                    <div class="form-group">
                        <label for="assigned_to">Assigned To</label>
                        <select id="assigned_to" name="assigned_to">
                            <option value="">Unassigned</option>
                            @foreach ($assignees as $assignee)
                                <option
                                    value="{{ $assignee->id }}"
                                    @selected((int) old('assigned_to', $ticket->assigned_to) === $assignee->id)
                                >
                                    {{ $assignee->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    {{-- ถ้าไม่มีสิทธิ์จัดการ assignment แสดงชื่อคนที่ได้รับมอบหมายแบบอ่านอย่างเดียว --}}
                    <div class="form-group">
                        <label>Assigned To</label>
                        <input
                            type="text"
                            value="{{ $ticket->assignee?->name ?? 'Unassigned' }}"
                            disabled
                        >
                    </div>
                @endif

                {{-- สถานะของ ticket (สำหรับ admin/owner แก้ได้เต็ม) --}}
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        @foreach ($statusOptions as $value => $definition)
                            <option value="{{ $value }}" @selected(old('status', $ticket->status) === $value)>
                                {{ $definition['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- หมายเหตุเกี่ยวกับการเปลี่ยนสถานะ (optional) --}}
                <div class="form-group">
                    <label for="status_note">Status Note</label>
                    <textarea
                        id="status_note"
                        name="status_note"
                        placeholder="Explain why the status changed (optional)"
                    >{{ old('status_note') }}</textarea>
                </div>

                {{-- อัปโหลดไฟล์แนบใหม่ (รูป/เอกสาร ฯลฯ) --}}
                <div class="form-group">
                    <label for="attachment">Attachment</label>
                    <input
                        id="attachment"
                        name="attachment"
                        type="file"
                        accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
                    >
                    {{-- ถ้าปัจจุบันมีไฟล์แนบอยู่แล้ว แสดงชื่อไฟล์และลิงก์เปิดดู --}}
                    @if ($ticket->attachment_path)
                        <small>
                            Current:
                            <a href="{{ Storage::url($ticket->attachment_path) }}" target="_blank">
                                {{ basename($ticket->attachment_path) }}
                            </a>
                        </small>
                    @endif
                </div>

                {{-- ปุ่มบันทึก/ยกเลิก --}}
                <div class="form-actions">
                    <a class="btn btn-cancel" href="{{ route('tickets.show', $ticket) }}">Cancel</a>
                    <button class="btn btn-submit" type="submit">Save</button>
                </div>
            </form>
        </section>
    @endif
@endsection
