@extends('layouts.auth') {{-- ใช้ layout สำหรับหน้าที่เกี่ยวกับการยืนยันตัวตน (auth layout) --}}

@section('title', 'Login') {{-- ตั้ง title หน้าเป็น "Login" --}}

@section('content')
    {{-- ฟอร์มล็อกอินเข้าสู่ระบบ --}}
    <form class="auth-form" action="{{ route('login.submit') }}" method="POST">
        @csrf {{-- token ป้องกัน CSRF ตามมาตรฐาน Laravel --}}

        <h2>Sign in</h2>
        <p class="auth-subtitle">
            Enter your credentials to access the ticket system.
            {{-- ข้อความอธิบายสั้น ๆ ใต้หัวข้อฟอร์ม --}}
        </p>

        {{-- แสดง error ถ้ามีการ validate ไม่ผ่าน หรือ login ไม่สำเร็จ --}}
        @if ($errors->any())
            <div class="auth-error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li> {{-- แสดงข้อความ error ทีละรายการ --}}
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ช่องกรอกอีเมล --}}
        <div class="form-group">
            <label for="email">Email</label>
            <input
                id="email"
                name="email"
                type="email"
                placeholder="name@example.com"
                value="{{ old('email') }}" {{-- ดึงค่าที่เคยกรอกไว้กลับมา ถ้ามี error --}}
            >
        </div>

        {{-- ช่องกรอกรหัสผ่าน --}}
        <div class="form-group">
            <label for="password">Password</label>
            <input
                id="password"
                name="password"
                type="password"
                placeholder="Enter password"
            >
        </div>

        {{-- ตัวเลือก Remember me เพื่อให้ระบบจำการล็อกอิน --}}
        <div class="auth-options">
            <label class="remember-checkbox">
                <input
                    type="checkbox"
                    name="remember"
                    value="1"
                    @checked(old('remember')) {{-- ถ้าเคยติ๊กไว้แล้ว validate ตก จะติ๊กกลับให้ --}}
                >
                <span>Remember me</span>
            </label>
        </div>

        {{-- ปุ่มกดล็อกอิน --}}
        <button type="submit" class="btn btn-primary auth-submit">
            Sign in
        </button>

        {{-- ข้อความช่วยเหลือเพิ่มเติมด้านล่างฟอร์ม --}}
        <div class="auth-meta">
            <p>
                Need help? Contact the System Admin or open a ticket via the portal.
            </p>
        </div>
    </form>
@endsection