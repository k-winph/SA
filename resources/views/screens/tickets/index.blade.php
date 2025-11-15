@extends('layouts.app') {{-- ใช้ layout หลักของระบบ --}}

@section('title', 'My Ticket') {{-- ตั้งชื่อหน้าใน <title> --}}

@section('content')
    <div class="page-header">
        {{-- ชื่อหัวข้อหน้า: ถ้ามีตัวแปร $pageTitle ใช้ค่านั้น ไม่งั้นใช้ "My Tickets" --}}
        <h1 class="page-title">{{ $pageTitle ?? 'My Tickets' }}</h1>

        <div class="page-actions">
            {{-- ถ้า user มีสิทธิ์สร้าง Ticket (ตาม Policy create) ให้แสดงปุ่ม Create --}}
            @can('create', \App\Models\Ticket::class)
                <a href="{{ route('tickets.create') }}" class="btn btn-primary">+ Create Ticket</a>
            @endcan
        </div>
    </div>

    <section class="card table-card">
        <div class="ticket-table-shell">
            {{-- ตารางแสดงรายการ Ticket --}}
            <table class="ticket-table">
                <thead>
                    <tr>
                        <th>Ticket ID</th>   {{-- รหัส Ticket เช่น TKT-0001 --}}
                        <th>Subject</th>     {{-- หัวข้อปัญหา --}}
                        <th>Priority</th>    {{-- ระดับความสำคัญ --}}
                        <th>Status</th>      {{-- สถานะ --}}
                        <th>Actions</th>     {{-- ปุ่มกดต่าง ๆ --}}
                    </tr>
                </thead>
                <tbody>
                    {{-- วนลูปแสดง Ticket ทีละรายการ ถ้าไม่มีจะไปเข้า @empty --}}
                    @forelse ($tickets as $ticket)
                        @php
                            // ดึงข้อมูลสถานะจาก $statusOptions ถ้าไม่พบ ใช้ label เป็นชื่อ status เดิม
                            $statusDefinition = $statusOptions[$ticket->status]
                                ?? ['label' => ucfirst($ticket->status), 'color' => '#1f71ff'];

                            // ดึง label ของ priority ถ้าไม่มีใน options ให้ใช้ชื่อเดิมตัวแรกใหญ่
                            $priorityLabel = $priorityOptions[$ticket->priority]
                                ?? ucfirst($ticket->priority);
                        @endphp
                        <tr>
                            {{-- แสดง Ticket ID เป็นฟอร์แมต TKT-0001 --}}
                            <td>{{ sprintf('TKT-%04d', $ticket->id) }}</td>

                            {{-- หัวข้อ Ticket --}}
                            <td>
                                <strong>{{ $ticket->subject }}</strong>
                            </td>

                            {{-- ระดับความสำคัญของ Ticket --}}
                            <td>{{ $priorityLabel }}</td>

                            {{-- สถานะของ Ticket แสดงเป็น pill สีตาม color ใน $statusDefinition --}}
                            <td>
                                <span class="status-pill"
                                      style="background: {{ $statusDefinition['color'] }}1a; color: {{ $statusDefinition['color'] }}">
                                    {{ strtoupper($statusDefinition['label']) }}
                                </span>
                            </td>

                            {{-- ปุ่มกดสำหรับดูรายละเอียด / อัปเดต --}}
                            <td class="table-actions">
                                {{-- ปุ่มไปหน้าแสดงรายละเอียด Ticket --}}
                                <a class="btn ghost" href="{{ route('tickets.show', $ticket) }}">Details</a>

                                {{-- ถ้าเป็นหน้า “assigned view” (มุมมอง staff) และ ticket ยังไม่ closed ให้แสดงปุ่ม Update --}}
                                @if ($assignedView && $ticket->status !== 'closed')
                                    <a class="btn ghost" href="{{ route('tickets.edit', $ticket) }}">Update</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        {{-- กรณีไม่มี ticket เลย --}}
                        <tr>
                            <td colspan="5">No tickets yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- แสดง pagination ด้านล่าง --}}
    <div class="pagination-wrap">
        {{ $tickets->links() }}
    </div>
@endsection
