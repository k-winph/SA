@extends('layouts.app') {{-- ใช้ layout หลักจากไฟล์ layouts/app.blade.php --}}

@section('title', 'Dashboard') {{-- กำหนด title ของหน้าเป็น "Dashboard" --}}

@section('content')
    {{-- หัวข้อหลัก แสดงคำทักทายพร้อมชื่อ role เช่น Admin / Staff --}}
    <h1 class="page-title">Welcome, {{ $welcomeName }}</h1>

    {{-- ส่วนแสดงกล่องสถิติ (cards) --}}
    <div class="stats-grid">
        @foreach ($stats as $stat)
            @php
                // ดึงค่าต่าง ๆ จาก array ของ stat ถ้าไม่มีให้เป็นค่า default
                $isDisabled = $stat['disabled'] ?? false;
                $bg = $stat['bg'] ?? null;
                $valueColor = $stat['value_color'] ?? null;
                $labelColor = $stat['label_color'] ?? null;
            @endphp

            {{-- ถ้า card ไม่ถูก disable และมี url ให้คลิกได้ (ใช้ <a>) --}}
            @if (!$isDisabled && !empty($stat['url']))
                <a class="stat-card stat-card-link"
                   href="{{ $stat['url'] }}"
                   style="{{ $bg ? 'background: ' . $bg . ';' : '' }}">
                    <p class="stat-value"
                       style="{{ $valueColor ? 'color: ' . $valueColor . ';' : '' }}">
                        {{ $stat['value'] }} {{-- ตัวเลขสถิติ --}}
                    </p>
                    <p class="stat-label"
                       style="{{ $labelColor ? 'color: ' . $labelColor . ';' : '' }}">
                        {{ $stat['label'] }} {{-- ชื่อสถิติ เช่น Total Tickets --}}
                    </p>
                </a>
            @else
                {{-- ถ้าไม่มี url หรือถูก disable ให้แสดงเป็น card ธรรมดา (คลิกไม่ได้) --}}
                <article class="stat-card stat-card-disabled"
                         style="{{ $bg ? 'background: ' . $bg . ';' : '' }}">
                    <p class="stat-value"
                       style="{{ $valueColor ? 'color: ' . $valueColor . ';' : '' }}">
                        {{ $stat['value'] }}
                    </p>
                    <p class="stat-label"
                       style="{{ $labelColor ? 'color: ' . $labelColor . ';' : '' }}">
                        {{ $stat['label'] }}
                    </p>
                </article>
            @endif
        @endforeach
    </div>

    {{-- ถ้ามีประวัติ ticket ล่าสุด (ใช้ในกรณี Staff) --}}
    @if (!empty($historyTickets))
        <section class="card history-card">
            <h2>History</h2>
            <div class="table-card">
                <table class="ticket-table">
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Subject</th>
                            <th>Priority</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- วนลูปแสดง ticket ย้อนหลัง ถ้าไม่มีใช้ @empty --}}
                        @forelse ($historyTickets as $history)
                            @php
                                // แปลง priority ให้เป็น label สวย ๆ ตาม mapping ที่ส่งมาจาก controller
                                $priorityLabel = $priorityLabels[$history->priority] ?? ucfirst($history->priority);
                                // ดึง label ของ status จาก config ถ้าไม่มีให้ใช้ชื่อ status แบบตัวอักษรใหญ่ตัวแรก
                                $statusLabel = config('ticketing.statuses.' . $history->status . '.label')
                                    ?? ucfirst(str_replace('_', ' ', $history->status));
                            @endphp
                            <tr>
                                {{-- แสดง Ticket ID ในรูปแบบ TKT-0001 --}}
                                <td>TKT-{{ str_pad((string) $history->id, 4, '0', STR_PAD_LEFT) }}</td>
                                <td class="truncate">
                                    <strong>{{ $history->subject }}</strong> {{-- หัวข้อ ticket --}}
                                </td>
                                <td>
                                    {{-- แสดง priority เป็น pill พร้อม class ตามระดับ priority --}}
                                    <span class="priority-pill priority-{{ $history->priority }}">
                                        {{ $priorityLabel }}
                                    </span>
                                </td>
                                <td>
                                    {{-- แสดง status เป็น pill สีต่าง ๆ ตามสถานะ --}}
                                    <span class="status-pill status-pill-{{ str_replace('_', '-', $history->status) }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            {{-- กรณีไม่มีประวัติ ticket เลย --}}
                            <tr>
                                <td colspan="4" class="muted">No history yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @else
        {{-- ถ้าไม่มี historyTickets (เช่น Admin หรือ user ทั่วไป) ให้แสดงส่วน Quick Action + กราฟสถานะแทน --}}
        <div class="grid-two">
            {{-- ส่วนปุ่มลัด (Quick Action) ไปหน้าต่าง ๆ --}}
            <section class="card">
                <h2>Quick Action</h2>
                <div class="quick-actions">
                    @foreach ($quickActions as $action)
                        <a class="quick-action" href="{{ $action['url'] }}">
                            {{-- icon สั้น ๆ เช่น MU, +, TK --}}
                            <span class="qa-icon">{{ $action['icon'] }}</span>
                            <span>{{ $action['label'] }}</span> {{-- ชื่อปุ่มลัด --}}
                        </a>
                    @endforeach
                </div>
            </section>

            {{-- ส่วนกราฟ/แถบแสดงจำนวน ticket ตามสถานะ --}}
            <section class="card">
                <h2>Ticket Status</h2>
                <div class="ticket-status-chart">
                    @foreach ($ticketStatus as $status)
                        <div class="bar">
                            {{-- ใช้ CSS custom properties (--value, --bar-color) ในการวาดความยาว bar และสี --}}
                            <div class="meter"
                                 style="--value: {{ $status['value'] }}; --bar-color: {{ $status['color'] ?? '#1f71ff' }};">
                            </div>
                            {{-- แสดงชื่อสถานะและจำนวนในวงเล็บ --}}
                            <span>{{ $status['label'] }} ({{ $status['value'] }})</span>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    @endif

@endsection
