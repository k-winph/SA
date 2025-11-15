<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    {{-- กำหนดให้หน้าเว็บแสดงผลแบบ responsive บนมือถือ/จอเล็ก --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- กำหนด title ของหน้า ถ้าไม่มี section title จะใช้ค่า default เป็น IT Support Ticket System --}}
    <title>@yield('title', 'IT Support Ticket System')</title>

    {{-- โหลดไฟล์ CSS หลักสำหรับหน้า auth (login ฯลฯ) --}}
    <link rel="stylesheet" href="{{ asset('css/itsupport.css') }}">

    @stack('styles')
</head>
<body class="auth-body">
    {{-- กล่องครอบทั้งหน้า auth --}}
    <div class="auth-wrapper">
        <div class="auth-panel">
            {{-- ส่วนแสดงโลโก้และชื่อระบบด้านบนฟอร์ม --}}
            <div class="auth-brand">
                {{-- โลโก้ตัวอักษรสั้น ๆ IT --}}
                <div class="auth-logo">IT</div>
                <div>
                    <h1>IT Support Ticket System</h1>
                    <p>Secure access for User, Staff, and Admin.</p>
                </div>
            </div>

            {{-- ส่วนเนื้อหาของแต่ละหน้า (เช่น ฟอร์ม Login) จะมาแทรกตรงนี้ --}}
            @yield('content')
        </div>
    </div>

    @stack('scripts')
</body>
</html>