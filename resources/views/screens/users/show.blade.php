@extends('layouts.app') {{-- ใช้ layout หลักจากไฟล์ layouts/app.blade.php --}}

@section('title', 'User Access Notice') {{-- ตั้งชื่อหน้าใน <title> เป็น User Access Notice --}}

@section('content')
    {{-- ส่วนหัวของหน้า + ปุ่มไปหน้าแก้ไข user --}}
    <div class="page-header">
        <h1 class="page-title">User Access Notice</h1>
        {{-- ปุ่มไปหน้าแก้ไข user ปัจจุบัน --}}
        <a class="btn btn-primary" href="{{ route('users.edit', $user) }}">
            Edit {{ $user->name }}
        </a>
    </div>

    {{-- การ์ดแสดงข้อความแจ้งเตือนเกี่ยวกับการเข้าถึงโปรไฟล์ผู้ใช้ --}}
    <section class="card">
        <p>
            {{-- ข้อความแจ้งว่าห้ามเปิดดู profile user โดยตรงเพื่อความปลอดภัย --}}
            Direct viewing of user profiles is disabled for security reasons.
            If you need to update
            <strong>{{ $user->name }}</strong>, please use the
            {{-- ลิงก์ไปที่ฟอร์มจัดการ/แก้ไขผู้ใช้แทน --}}
            <a href="{{ route('users.edit', $user) }}">Manage User form</a>.
        </p>
        <p style="margin-top:1rem;">
            {{-- ปุ่มกลับไปหน้ารายการจัดการผู้ใช้ทั้งหมด --}}
            <a class="btn ghost" href="{{ route('users.index') }}">
                Back to Manage Users
            </a>
        </p>
    </section>
@endsection