@extends('layouts.app') {{-- ใช้ layout หลักจากไฟล์ layouts/app.blade.php --}}

@section('title', 'Edit User') {{-- ตั้ง title ของหน้าเป็น "Edit User" --}}

@section('content')
    @php
        // ตัวเลือก role ต่าง ๆ ที่ใช้ในฟอร์ม (mapping จากค่าคงที่ใน Model ไปเป็นข้อความที่อ่านง่าย)
        $roleOptions = [
            \App\Models\User::ROLE_ADMIN => 'Admin',
            \App\Models\User::ROLE_STAFF => 'Staff',
            \App\Models\User::ROLE_USER  => 'User',
        ];

        // เช็คว่าผู้ใช้ที่กำลังถูกแก้ไขคือคนเดียวกับผู้ที่ล็อกอินอยู่ตอนนี้หรือไม่
        // ถ้าใช่ จะไม่ให้ตัวเองปิด active ตัวเองได้
        $isSelf = auth()->id() === $user->id;
    @endphp

    {{-- หัวกระดาษ แสดงชื่อผู้ใช้ที่กำลังถูกแก้ไข --}}
    <div class="page-header">
        <h1 class="page-title">Edit {{ $user->name }}</h1>
    </div>

    {{-- การ์ดฟอร์มสำหรับแก้ไขข้อมูลผู้ใช้ --}}
    <section class="card form-card">
        {{-- ฟอร์มส่งไปอัปเดต user (method PUT) --}}
        <form action="{{ route('users.update', $user) }}" method="post">
            @csrf
            @method('PUT')

            {{-- ฟิลด์ชื่อ Name --}}
            <div class="form-group">
                <label for="name">Name</label>
                {{-- old() ใช้ดึงค่าจากการ submit ก่อนหน้า ถ้ามี error จะรักษาค่าเดิมไว้ ไม่หาย --}}
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}">
            </div>

            {{-- ฟิลด์ Email --}}
            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}">
            </div>

            {{-- กลุ่มฟิลด์ Role แสดงเป็น radio ให้เลือก Admin / Staff / User --}}
            <div class="form-group role-group">
                <label for="role">Role</label>
                <div class="role-options">
                    @foreach ($roleOptions as $value => $label)
                        <label class="role-option">
                            <input
                                id="role_{{ $value }}"
                                type="radio"
                                name="role"
                                value="{{ $value }}"
                                {{-- @checked จะเช็คว่าค่าที่เลือกตรงกับ role ปัจจุบันหรือค่าที่ส่งมาก่อนหน้าไหม --}}
                                @checked(old('role', $user->role) === $value)
                            >
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- ฟิลด์ checkbox สำหรับเปิด/ปิดการใช้งานบัญชีผู้ใช้ --}}
            <div class="form-group checkbox-field">
                <label class="checkbox-label" for="is_active_edit">
                    {{-- ถ้าเป็น user คนเดียวกับที่ล็อกอินอยู่ตอนนี้ จะ disabled ไม่ให้ปิด active ตัวเอง --}}
                    <input
                        id="is_active_edit"
                        type="checkbox"
                        name="is_active"
                        value="1"
                        @checked(old('is_active', $user->is_active))
                        @disabled($isSelf)
                    >
                    Account is active
                </label>

                {{-- ถ้าเป็นตัวเอง ให้ส่งค่า is_active ผ่าน hidden field แทน (เพื่อไม่ให้โดนเปลี่ยนจาก checkbox ที่ disabled) --}}
                @if ($isSelf)
                    <input type="hidden" name="is_active" value="{{ $user->is_active ? 1 : 0 }}">
                @endif

                {{-- ข้อความอธิบายสถานะการ suspend / disable บัญชี --}}
                <small class="text-muted">
                    @if ($user->deactivated_at)
                        {{-- ถ้าเคยถูก suspend จะแสดงว่าถูก suspend มานานเท่าไหร่แล้ว --}}
                        Suspended {{ $user->deactivated_at->diffForHumans() }} · re-enable to restore access.
                    @else
                        {{-- ข้อความแนะนำว่า disable แค่ระงับชั่วคราว ไม่ได้ลบ user ทิ้ง --}}
                        Disable to temporarily suspend access without deleting the user.
                    @endif
                </small>
            </div>

            {{-- ฟิลด์ตั้งรหัสผ่านใหม่ (optional ถ้าเว้นว่างจะไม่เปลี่ยนรหัสผ่าน) --}}
            <div class="form-group">
                <label for="password">Set New Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="Enter new password (optional)"
                >
            </div>

            {{-- ฟิลด์ยืนยันรหัสผ่านใหม่ --}}
            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    placeholder="Confirm new password"
                >
            </div>

            {{-- ปุ่มด้านล่างฟอร์ม: Cancel และ Save --}}
            <div class="form-actions">
                {{-- ปุ่มยกเลิก กลับไปหน้ารายการผู้ใช้ --}}
                <a class="btn btn-cancel" href="{{ route('users.index') }}">Cancel</a>

                {{-- ปุ่ม submit บันทึกการเปลี่ยนแปลง --}}
                <button class="btn btn-submit" type="submit">Save Changes</button>
            </div>
        </form>
    </section>

@endsection
