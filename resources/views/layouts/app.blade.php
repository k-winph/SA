<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    {{-- ให้หน้าเว็บ responsive กับมือถือ/จอเล็ก --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- ตั้ง title ของหน้า ถ้าไม่กำหนด section('title') จะใช้ "IT Support Ticket System" --}}
    <title>@yield('title', 'IT Support Ticket System')</title>

    {{-- โหลดไฟล์ CSS หลักของระบบ --}}
    <link rel="stylesheet" href="{{ asset('css/itsupport.css') }}">

    {{-- เอาไว้ให้หน้าอื่น ๆ สามารถ push CSS เพิ่มเติมเข้ามาได้ --}}
    @stack('styles')
</head>
<body>
    @php
        // ดึง user ที่ล็อกอินอยู่ตอนนี้
        $user = auth()->user();

        // กำหนดเมนูด้านข้าง (sidebar) ตาม role และสิทธิ์
        $navItems = collect([
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'icon' => 'DB',
                'url' => route('dashboard.admin'),
                'roles' => [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_STAFF], // ให้ Admin/Staff เห็น
            ],
            [
                'key' => 'users',
                'label' => 'Manage Users',
                'icon' => 'MU',
                'url' => route('users.index'),
                'roles' => [\App\Models\User::ROLE_ADMIN], // เฉพาะ Admin
            ],
            [
                'key' => 'manage-tasks',
                'label' => 'Manage Task',
                'icon' => 'MT',
                'url' => route('admin.tasks.index'),
                'roles' => [\App\Models\User::ROLE_ADMIN], // เฉพาะ Admin
            ],
            [
                'key' => 'my-ticket',
                'label' => 'My Tickets',
                'icon' => 'TK',
                'url' => route('tickets.index'),
                'roles' => [\App\Models\User::ROLE_STAFF, \App\Models\User::ROLE_USER], // Staff + End user
            ],
            [
                'key' => 'create-ticket',
                'label' => 'Create Ticket',
                'icon' => '+',
                'url' => route('tickets.create'),
                'roles' => [\App\Models\User::ROLE_USER], // เฉพาะ End user
            ],
            [
                'key' => 'account-password',
                'label' => 'Change Password',
                'icon' => 'PW',
                'url' => route('account.password.edit'),
                'roles' => [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_STAFF, \App\Models\User::ROLE_USER], // ทุก role ที่ล็อกอิน
            ],
        ])
            // filter เมนูตาม role ของ user
            ->filter(function ($item) use ($user) {
                // ถ้ายังไม่ได้ล็อกอิน (ไม่มี user) ให้ผ่านได้ (กรณีบางหน้าอาจใช้ layout เดียวกัน)
                if (!$user) {
                    return true;
                }

                // ถ้าไม่กำหนด roles หรือ user มี role ใด role หนึ่งใน list ก็ให้โชว์เมนูนั้น
                return empty($item['roles']) || $user->hasAnyRole($item['roles']);
            })
            ->values()
            ->all();

        // ตั้งค่า default ของ activePage ถ้าไม่ได้ถูกส่งมาจาก view ลูก
        $activePage = $activePage ?? 'dashboard';
    @endphp

    {{-- แถบบนสุดของหน้า (Top bar) --}}
    <header class="top-bar">
        <div class="brand">IT Support Ticket System</div>

        {{-- ส่วนขวาบนเฉพาะตอนที่มีการล็อกอินแล้ว --}}
        @auth
            @php
                // นับจำนวน notification ที่ยังไม่ได้อ่าน
                $unreadNotifications = $user->unreadNotifications()->count();
            @endphp
            <div class="header-actions">
                {{-- ปุ่มกระดิ่ง notification --}}
                <a href="{{ route('notifications.index') }}" class="notification-bell">
                    🔔
                    {{-- ถ้ามี noti ที่ยังไม่ได้อ่าน แสดงตัวเลข --}}
                    @if ($unreadNotifications > 0)
                        <span class="notification-count">{{ $unreadNotifications }}</span>
                    @endif
                </a>

                {{-- ข้อมูลโปรไฟล์ย่อ ๆ + ปุ่ม logout --}}
                <div class="profile-info">
                    @php
                        $displayName = $user->name;                             // ชื่อเต็มของ user
                        $initial = strtoupper(mb_substr($displayName, 0, 1));   // ตัวอักษรตัวแรก (เอาไว้ใช้เป็น avatar)
                    @endphp
                    <span class="profile-name">{{ $displayName }}</span>
                    <div class="profile-badge">
                        <span class="profile-icon">{{ $initial }}</span>
                    </div>

                    {{-- ฟอร์ม logout แบบ POST ตามมาตรฐาน Laravel --}}
                    <form class="logout-form" action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="logout-btn">Logout</button>
                    </form>
                </div>
            </div>
        @endauth
    </header>

    <div class="page">
        {{-- แถบเมนูด้านข้าง (Sidebar) --}}
        <aside class="sidebar">
            <nav>
                @foreach ($navItems as $item)
                    {{-- ถ้า key ตรงกับ $activePage จะใส่ class active เพื่อไฮไลต์เมนู --}}
                    <a href="{{ $item['url'] }}" class="{{ $activePage === $item['key'] ? 'active' : '' }}">
                        <span class="icon">{{ $item['icon'] }}</span>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>

        {{-- เนื้อหาหลักของหน้า --}}
        <main class="content">
            {{-- แสดงข้อความ success จาก session (เช่น "บันทึกสำเร็จ") ถ้ามี --}}
            @if (session('status'))
                <div class="alert success">{{ session('status') }}</div>
            @endif

            {{-- แสดง error จาก validation ต่าง ๆ ถ้ามี --}}
            @if ($errors->any())
                <div class="alert danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li> {{-- แสดง error ทีละบรรทัด --}}
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ตำแหน่งที่แต่ละหน้า (view ลูก) จะใส่ content ของตัวเอง --}}
            @yield('content')
        </main>
    </div>

    {{-- เอาไว้ให้ view ลูก push script JS เพิ่มเติมเข้ามา --}}
    @stack('scripts')
</body>
</html>
