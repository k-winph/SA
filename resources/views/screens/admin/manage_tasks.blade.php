@extends('layouts.app') {{-- ใช้ layout หลักของระบบ --}}

@section('title', 'Manage Task') {{-- ตั้ง title หน้าเป็น "Manage Task" --}}

@section('content')
    {{-- หัวข้อหลักของหน้า --}}
    <h1 class="page-title">Manage Task</h1>

    {{-- การ์ดครอบตารางแสดง Ticket ที่ต้องจัดการ --}}
    <section class="card table-card">
        <div class="table-responsive">
            <table class="ticket-table">
                <thead>
                    <tr>
                        <th>Ticket ID</th>   {{-- รหัส ticket --}}
                        <th>Owner</th>       {{-- คนสร้าง ticket --}}
                        <th>Subject</th>     {{-- หัวข้อปัญหา --}}
                        <th>Assignee</th>    {{-- คนที่ถูก assign ให้ทำงาน --}}
                        <th>Status</th>      {{-- สถานะ ticket --}}
                        <th style="width:320px;">Actions</th> {{-- ปุ่มคำสั่งต่าง ๆ --}}
                    </tr>
                </thead>
                <tbody>
                    {{-- วนลูป ticket ทั้งหมด ถ้าไม่มีจะไป @empty --}}
                    @forelse ($tickets as $ticket)
                        <tr>
                            {{-- แสดง id ของ ticket ตรง ๆ (ถ้าจะใช้ฟอร์แมต TKT-0001 ค่อยไปแก้เพิ่ม) --}}
                            <td>{{ $ticket->id }}</td>

                            {{-- ชื่อเจ้าของ ticket (creator) ถ้าไม่มีให้แสดง — --}}
                            <td>{{ optional($ticket->creator)->name ?? '—' }}</td>

                            {{-- หัวข้อ ticket ถ้า subject เป็น null ให้แสดง — และตัดข้อความด้วย class truncate --}}
                            <td class="truncate">{{ $ticket->subject ?? '—' }}</td>

                            {{-- ชื่อผู้รับมอบหมาย (assignee) ถ้าไม่มีให้แสดง — --}}
                            <td>{{ optional($ticket->assignee)->name ?? '—' }}</td>

                            {{-- แสดงสถานะ ticket เป็น badge สี ๆ --}}
                            <td>
                                @php($def = $ticket->getStatusDefinition()) {{-- ดึง label และ color ของ status จาก model --}}
                                <span class="status-badge"
                                      style="background: {{ $def['color'] }}20; color: {{ $def['color'] }}; padding: 4px 10px; border-radius: 999px; font-weight: 700; font-size: .85rem;">
                                    {{ $def['label'] }}
                                </span>
                            </td>

                            {{-- คอลัมน์ Actions: ดูรายละเอียด / แก้ไข / Assign / ลบ --}}
                            <td>
                                <div class="table-actions">
                                    {{-- ปุ่มไปหน้าแสดงรายละเอียด ticket --}}
                                    <a class="btn ghost" href="{{ route('tickets.show', $ticket) }}">Details</a>

                                    {{-- ปุ่มแก้ไข ticket (โชว์เมื่อมีสิทธิ์ update ตาม Policy) --}}
                                    @can('update', $ticket)
                                        <a class="btn edit" href="{{ route('tickets.edit', $ticket) }}">Edit</a>
                                    @endcan

                                    {{-- ปุ่มเปิด modal สำหรับ Assign ticket ให้ staff คนหนึ่ง --}}
                                    <button
                                        type="button"
                                        class="btn btn-primary open-assign-modal"
                                        data-ticket-id="{{ $ticket->id }}"        {{-- ส่ง id ของ ticket ไปให้ JS --}}
                                        data-assignee-id="{{ $ticket->assigned_to ?? '' }}" {{-- ส่ง assignee id ปัจจุบันไปให้ JS --}}
                                    >Assign</button>

                                    {{-- ปุ่มลบ ticket (โชว์เมื่อมีสิทธิ์ delete ตาม Policy) --}}
                                    @can('delete', $ticket)
                                        <form action="{{ route('tickets.destroy', $ticket) }}"
                                              method="POST"
                                              onsubmit="return confirm('Delete this ticket?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn delete">Delete</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        {{-- กรณีไม่มี ticket ในระบบเลย --}}
                        <tr>
                            <td colspan="6" style="text-align:center; padding: 1rem;">No tickets found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- แสดง pagination ด้วย template bootstrap-5 --}}
        <div style="margin-top: 1rem;">
            {{ $tickets->links('vendor.pagination.bootstrap-5') }}
        </div>
    </section>

    {{-- CSS สำหรับ modal Assign --}}
    @push('styles')
        <style>
            .modal-overlay {
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.45); /* พื้นหลังดำโปร่ง ๆ ทับทั้งจอ */
                display: none;
                align-items: center;
                justify-content: center;
                z-index: 1000;
            }
            .modal-overlay.show { display: flex; } /* เวลาเปิด modal ให้ใช้ class show */

            .modal-panel {
                background: #fff;
                width: min(560px, 92vw);
                border-radius: 18px;
                box-shadow: 0 30px 80px rgba(15,23,42,.35);
                padding: 1.25rem 1.25rem 1rem;
            }
            .modal-title {
                margin: .2rem 0 1rem;
                font-size: 1.6rem;
                text-align: center;
            }
            .modal-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: .8rem;
                margin-top: 1rem;
            }
            .btn-cancel { background: #fecaca; color: #7f1d1d; }  /* ปุ่ม Cancel = โทนแดงอ่อน */
            .btn-save { background: #34d399; color: #064e3b; }    /* ปุ่ม Save = โทนเขียว */
            .btn .btn-icn { margin-right: .45rem; }

            /* สไตล์ select พร้อม icon ลูกศร */
            .select-wrap { position: relative; }
            .select-wrap select { width: 100%; appearance: none; padding-right: 2.2rem; }
            .select-caret {
                position: absolute;
                right: .65rem;
                top: 50%;
                transform: translateY(-50%);
                pointer-events: none;
                color: var(--text-muted);
                display: inline-flex;
                align-items: center;
            }
            .select-caret svg { width: 18px; height: 18px; }
        </style>
    @endpush

    {{-- Modal สำหรับ Assign ticket ให้ staff --}}
    <div id="assign-modal" class="modal-overlay" aria-hidden="true">
        <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="assign-modal-title">
            <h2 id="assign-modal-title" class="modal-title">Assign Task</h2>

            {{-- ฟอร์มเลือก staff เพื่อ assign (action จะถูกเซ็ตด้วย JS ตอนเปิด modal) --}}
            <form id="assign-form" method="POST" action="#">
                @csrf
                <div class="form-group">
                    <label for="assignee_id">Assignee</label>
                    <div class="select-wrap">
                        <select id="assignee_id" name="assignee_id">
                            <option value="">-- Select Staff --</option>
                            {{-- แสดงรายชื่อ staff ทุกคนใน dropdown --}}
                            @foreach ($staff as $member)
                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                            @endforeach
                        </select>
                        <span class="select-caret" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd"
                                      d="M5.23 7.21a.75.75 0 011.06.02L10 11.188l3.71-3.958a.75.75 0 111.08 1.04l-4.24 4.52a.75.75 0 01-1.08 0l-4.24-4.52a.75.75 0 01.02-1.06z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </span>
                    </div>
                </div>

                {{-- ปุ่มใน modal: Cancel / Save --}}
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" id="assign-cancel">
                        <span class="btn-icn"></span>Cancel
                    </button>
                    <button type="submit" class="btn btn-save">
                        <span class="btn-icn"></span>Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- สคริปต์จัดการการเปิด/ปิด modal และตั้งค่า form action --}}
    @push('scripts')
        <script>
            (function () {
                const overlay = document.getElementById('assign-modal');    // ตัว overlay ทับหน้าจอ
                const form = document.getElementById('assign-form');        // ฟอร์ม assign
                const select = document.getElementById('assignee_id');      // dropdown เลือก staff
                const cancelBtn = document.getElementById('assign-cancel'); // ปุ่ม Cancel
                // template URL สำหรับส่ง assign (ใช้ __ID__ แทนที่ด้วย ticket id ทีหลัง)
                const actionTemplate = "{{ route('admin.tasks.assign', ['ticket' => '__ID__']) }}";

                // ฟังก์ชันเปิด modal พร้อมเซ็ต action และค่า assignee ปัจจุบัน (ถ้ามี)
                function openModal(ticketId, assigneeId) {
                    // เปลี่ยน __ID__ ใน template ให้เป็น ticketId จริง
                    form.action = actionTemplate.replace('__ID__', ticketId);

                    // ถ้ามี assignee เดิมอยู่แล้ว ให้เลือกค่าตามนั้น
                    if (assigneeId) {
                        select.value = String(assigneeId);
                    } else {
                        select.value = '';
                    }

                    // แสดง modal
                    overlay.classList.add('show');
                    overlay.removeAttribute('aria-hidden');
                    select.focus();
                }

                // ฟังก์ชันปิด modal
                function closeModal() {
                    overlay.classList.remove('show');
                    overlay.setAttribute('aria-hidden', 'true');
                }

                // ผูก event กับปุ่ม Assign ในแต่ละแถวของตาราง
                document.querySelectorAll('.open-assign-modal').forEach(btn => {
                    btn.addEventListener('click', () => {
                        // ดึง ticket id และ assignee id จาก data-* attributes
                        openModal(btn.dataset.ticketId, btn.dataset.assigneeId || '');
                    });
                });

                // ปุ่ม Cancel ใน modal ปิดหน้าต่าง
                cancelBtn.addEventListener('click', closeModal);

                // คลิกพื้นหลัง (overlay) เพื่อปิด modal
                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) closeModal();
                });

                // กดปุ่ม Escape เพื่อปิด modal
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') closeModal();
                });
            })();
        </script>
    @endpush
@endsection
