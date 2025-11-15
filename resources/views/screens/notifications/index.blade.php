@extends('layouts.app') {{-- ใช้ layout หลักของระบบ --}}

@section('title', 'Notifications') {{-- ตั้ง title หน้าเป็น Notifications --}}

@section('content')
    {{-- ส่วนหัวของหน้า --}}
    <div class="page-header">
        <h1 class="page-title">Notifications</h1>
    </div>

    <section class="card">
        {{-- รายการแจ้งเตือนทั้งหมด --}}
        <ul class="notification-list">
            {{-- วนลูป notification ถ้าไม่มีเลยจะไปที่ @empty --}}
            @forelse ($notifications as $notification)
                @php
                    // ข้อมูลหลักของ notification เก็บอยู่ใน $notification->data (array/json)
                    $data = $notification->data;

                    // ถ้าเป็น event ประเภท comment / comment_update จะไม่แสดง status ต่อท้าย
                    $hideStatusMeta = in_array($data['event_type'] ?? '', ['comment', 'comment_update'], true);
                @endphp

                {{-- ถ้าอ่านแล้ว (มี read_at) ไม่ใส่ class unread ถ้ายังไม่อ่านจะใส่ unread เพื่อเปลี่ยนสไตล์ --}}
                <li class="{{ $notification->read_at ? '' : 'unread' }}">
                    <div>
                        {{-- ข้อความหลักของ notification ถ้าไม่มี message ใน data ให้ใช้คำว่า "Ticket update" แทน --}}
                        <p class="notification-message">
                            {{ $data['message'] ?? 'Ticket update' }}
                        </p>

                        {{-- ข้อมูลย่อยของ notification เช่น event type / ticket / status --}}
                        <p class="notification-meta">
                            {{-- ประเภท event เช่น STATUS_CHANGE, COMMENT ฯลฯ เป็นตัวพิมพ์ใหญ่ --}}
                            <span class="event-type">
                                {{ strtoupper($data['event_type'] ?? 'UPDATE') }}
                            </span>

                            {{-- ลิงก์ไปที่ ticket ที่เกี่ยวข้อง ถ้าไม่มี link ให้ใช้ "#" --}}
                            Ticket:
                            <a href="{{ $data['link'] ?? '#' }}">
                                #{{ $data['ticket_id'] ?? '' }} {{ $data['subject'] ?? '' }}
                            </a>

                            {{-- ถ้าไม่ใช่ event ประเภท comment / comment_update แสดงสถานะของ ticket ด้วย --}}
                            @unless($hideStatusMeta)
                                &middot;
                                Status: {{ ucfirst(str_replace('_', ' ', $data['status'] ?? '')) }}
                            @endunless
                        </p>
                    </div>

                    {{-- ถ้ายังไม่อ่าน แสดง badge "New" ทางขวา --}}
                    @if (!$notification->read_at)
                        <span class="badge">New</span>
                    @endif
                </li>
            @empty
                {{-- กรณียังไม่มี notification เลย --}}
                <li>No notifications yet.</li>
            @endforelse
        </ul>

        {{-- ปุ่มเปลี่ยนหน้าของ pagination --}}
        <div class="pagination-wrap">
            {{ $notifications->links() }}
        </div>
    </section>
@endsection