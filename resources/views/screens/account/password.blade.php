@extends('layouts.app') {{-- ใช้ layout หลักของระบบ --}}

@section('title', 'Change Password') {{-- ตั้ง title หน้าเป็น "Change Password" --}}

@section('content')
    {{-- ส่วนหัวของหน้า --}}
    <div class="page-header">
        <h1 class="page-title">Change Password</h1>
    </div>

    {{-- การ์ดฟอร์มสำหรับเปลี่ยนรหัสผ่านของบัญชีตัวเอง --}}
    <section class="card form-card">
        {{-- ฟอร์มส่งไปยัง route account.password.update เพื่อเปลี่ยนรหัสผ่าน --}}
        <form action="{{ route('account.password.update') }}" method="post">
            @csrf {{-- ป้องกัน CSRF ตามมาตรฐาน Laravel --}}

            {{-- ฟิลด์กรอกรหัสผ่านปัจจุบัน เพื่อยืนยันตัวตนก่อนเปลี่ยนรหัสใหม่ --}}
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input
                    id="current_password"
                    name="current_password"
                    type="password"
                    placeholder="Enter current password"
                    required
                >
            </div>

            {{-- ฟิลด์กรอกรหัสผ่านใหม่ --}}
            <div class="form-group">
                <label for="password">New Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="Enter new password"
                    required
                >
                {{-- เงื่อนไขเบื้องต้นของรหัสผ่านใหม่ (อย่างน้อย 8 ตัวอักษร) --}}
                <small class="text-muted">
                    Must be at least 8 characters.
                </small>
            </div>

            {{-- ฟิลด์ยืนยันรหัสผ่านใหม่ ต้องตรงกับ New Password --}}
            <div class="form-group">
                <label for="password_confirmation">Confirm New Password</label>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    placeholder="Confirm new password"
                    required
                >
            </div>

            {{-- ปุ่มยกเลิกและปุ่มบันทึกการเปลี่ยนรหัสผ่าน --}}
            <div class="form-actions">
                {{-- ปุ่ม Cancel กลับไปหน้า dashboard --}}
                <a class="btn btn-cancel" href="{{ route('dashboard.admin') }}">Cancel</a>

                {{-- ปุ่มส่งฟอร์มเพื่ออัปเดตรหัสผ่าน --}}
                <button class="btn btn-submit" type="submit">
                    Update Password
                </button>
            </div>
        </form>
    </section>
@endsection