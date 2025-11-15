@extends('layouts.app') {{-- ใช้ layout หลักจากไฟล์ layouts/app.blade.php --}}

@section('title', 'Create User') {{-- ตั้ง title ของหน้าเป็น "Create User" --}}

@section('content')
    {{-- ส่วนหัวของหน้า แสดงชื่อหน้า Create User --}}
    <div class="page-header">
        <h1 class="page-title">Create User</h1>
    </div>

    @php
        // ตัวเลือก role ต่าง ๆ สำหรับใช้ใน radio button
        $roleOptions = [
            \App\Models\User::ROLE_ADMIN => 'Admin',
            \App\Models\User::ROLE_STAFF => 'Staff',
            \App\Models\User::ROLE_USER  => 'User',
        ];
    @endphp

    {{-- การ์ดฟอร์มสร้างผู้ใช้ใหม่ --}}
    <section class="card form-card">
        {{-- ฟอร์มส่งไปที่ route users.store เพื่อบันทึก user ใหม่ --}}
        <form action="{{ route('users.store') }}" method="post">
            @csrf {{-- ป้องกัน CSRF ตามมาตรฐาน Laravel --}}

            {{-- ฟิลด์ชื่อผู้ใช้ --}}
            <div class="form-group">
                <label for="name">Name</label>
                <input
                    id="name"
                    name="name"
                    type="text"
                    placeholder="Enter Name"
                    value="{{ old('name') }}" {{-- ถ้า validate ไม่ผ่านจะดึงค่าที่เคยกรอกกลับมา --}}
                >
            </div>

            {{-- ฟิลด์อีเมล --}}
            <div class="form-group">
                <label for="email">Email</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    placeholder="Enter Email"
                    value="{{ old('email') }}"
                >
            </div>

            {{-- ฟิลด์เลือก Role ของผู้ใช้ (Admin / Staff / User) --}}
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
                                {{-- default ให้ Staff เป็นค่าเริ่มต้น ถ้ายังไม่เคยเลือก --}}
                                @checked(old('role', \App\Models\User::ROLE_STAFF) === $value)
                            >
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- ฟิลด์รหัสผ่าน --}}
            <div class="form-group">
                <label for="password">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="Enter Password"
                >
            </div>

            {{-- ฟิลด์ยืนยันรหัสผ่าน --}}
            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    placeholder="Confirm Password"
                >
            </div>

            {{-- ช่องติ๊กเลือกให้ account นี้ active ทันทีหรือไม่ --}}
            <div class="form-group checkbox-field">
                <label class="checkbox-label" for="is_active_create">
                    <input
                        id="is_active_create"
                        type="checkbox"
                        name="is_active"
                        value="1"
                        @checked(old('is_active', true)) {{-- ค่าเริ่มต้นให้เป็น true = เปิดใช้งานทันที --}}
                    >
                    Activate account immediately
                </label>
                <small class="text-muted">
                    {{-- คำอธิบาย: ถ้าไม่ติ๊ก จะสร้าง user แบบ suspended ไว้ก่อน --}}
                    Uncheck to create the account in a suspended state until onboarding is complete.
                </small>
            </div>

            {{-- ปุ่มด้านล่างฟอร์ม: Cancel และ Submit --}}
            <div class="form-actions">
                {{-- ปุ่มยกเลิก กลับไปหน้ารายการผู้ใช้ --}}
                <a class="btn btn-cancel" href="{{ route('users.index') }}">Cancel</a>

                {{-- ปุ่มส่งฟอร์มเพื่อสร้าง user --}}
                <button class="btn btn-submit" type="submit">Submit</button>
            </div>
        </form>
    </section>
@endsection
