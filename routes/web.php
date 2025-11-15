<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\TaskController as AdminTaskController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketCommentController;
use App\Models\User;
use App\Models\Ticket;
use App\Services\Ticket\TicketMetricsService; // service สำหรับดึงสถิติของ Ticket (เช่น จำนวน ticket แต่ละสถานะ)
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// หน้า login (GET) แสดงฟอร์มให้ล็อกอิน เข้าถึงได้เฉพาะ guest (คนที่ยังไม่ได้ล็อกอิน)
Route::get('/login', [AuthController::class, 'showLogin'])
    ->middleware('guest')
    ->name('login');

// ส่งฟอร์ม login (POST) ใช้ตรวจสอบ username/password เข้าถึงได้เฉพาะ guest
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('guest')
    ->name('login.submit');

// logout (POST) ออกจากระบบ ใช้ได้เฉพาะคนที่ล็อกอินอยู่แล้ว
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// กลุ่ม route ที่ต้องล็อกอินก่อนเท่านั้น (middleware auth)
Route::middleware('auth')->group(function () {
    // Dashboard หลักของระบบ (หน้าแรกหลังล็อกอิน)
    Route::get('/', function (TicketMetricsService $metrics) {
        $user = Auth::user(); // ดึง user ที่ล็อกอินปัจจุบัน

        // ถ้าเป็น Admin
        if ($user->isAdmin()) {
            // กล่องสถิติต่าง ๆ ที่จะไปแสดงบน Dashboard ของ Admin
            $stats = [
                [
                    'label' => 'Total Tickets',              // ป้ายชื่อ
                    'value' => $metrics->totalTickets(),     // จำนวน ticket ทั้งหมด
                    'url'   => route('tickets.index'),       // ลิงก์ไปหน้ารายการ ticket
                ],
                [
                    'label' => 'Open Ticket',
                    'value' => $metrics->countByStatus('open'), // นับ ticket สถานะ open
                    'url'   => route('tickets.index', ['status' => 'open']),
                ],
                [
                    'label' => 'Resolved Ticket',
                    'value' => $metrics->countByStatus('resolved'), // นับ ticket สถานะ resolved
                    'url'   => route('tickets.index', ['status' => 'resolved']),
                ],
                [
                    'label' => 'User',
                    'value' => User::count(),                   // จำนวน user ทั้งหมด
                    'url'   => route('users.index'),
                    'disabled' => false,
                ],
            ];

            // ชื่อที่ใช้แสดงต้อนรับบน Dashboard
            $welcomeName = 'Admin';

            // ข้อมูลจำนวน ticket แยกตามสถานะ สำหรับแสดงในกราฟ
            $statusForChart = collect($metrics->statusCounts())
                ->reject(fn ($status) => $status['status'] === 'testing') // ตัดสถานะ testing ไม่ให้แสดงในกราฟ
                ->values()
                ->all();

        // ถ้าเป็น Staff
        } elseif ($user->isStaff()) {
            // กล่องสถิติบน Dashboard ของ Staff (นับเฉพาะ ticket ที่เกี่ยวกับ staff คนนั้น)
            $stats = [
                [
                    'label' => 'In Progress Ticket',
                    'value' => $metrics->countByStatus('in_progress', $user),  // นับ ticket in_progress ของ staff คนนี้
                    'url'   => route('tickets.index', ['status' => 'in_progress']),
                    'bg'    => '#9EE7EF',   // สีพื้นหลังการ์ด
                    'value_color' => '#111827',
                    'label_color' => '#111827',
                ],
                [
                    'label' => 'Resolved Ticket',
                    'value' => $metrics->countByStatus('resolved', $user),    // นับ ticket resolved ของ staff คนนี้
                    'url'   => route('tickets.index', ['status' => 'resolved']),
                    'bg'    => '#ECEB76',
                    'value_color' => '#111827',
                    'label_color' => '#111827',
                ],
                [
                    'label' => 'Close Ticket',
                    'value' => $metrics->countByStatus('closed', $user),      // นับ ticket closed ของ staff คนนี้
                    'url'   => route('tickets.index', ['status' => 'closed']),
                    'bg'    => '#FF7070',
                    'value_color' => '#111827',
                    'label_color' => '#111827',
                ],
            ];

            // เตรียมข้อมูลกราฟแสดงจำนวน ticket แยกตามสถานะของ staff คนนี้
            $statusKeys = array_keys(config('ticketing.statuses', [])); // ดึงรายการ status ที่กำหนดไว้ใน config
            $counts = Ticket::assignedTo($user)                         // query ticket ที่ assigned ให้ staff คนนี้
                ->selectRaw('status, COUNT(*) as aggregate')
                ->whereIn('status', $statusKeys)
                ->groupBy('status')
                ->pluck('aggregate', 'status');                         // ได้เป็น array [status => count]

            // map แต่ละสถานะให้กลายเป็น array ของข้อมูลสำหรับส่งไปให้ view วาดกราฟ
            $statusForChart = collect($statusKeys)
                ->map(fn ($status) => [
                    'status' => $status,
                    'label'  => config("ticketing.statuses.{$status}.label") ?? ucfirst($status),
                    'color'  => config("ticketing.statuses.{$status}.color") ?? '#1f71ff',
                    'value'  => $counts[$status] ?? 0,
                ])
                ->values()
                ->all();

            // ชื่อที่ใช้แสดงบน Dashboard
            $welcomeName = 'Staff';

            // ประวัติ ticket ล่าสุดที่ถูก assign ให้ staff คนนี้ (ดึง 6 รายการล่าสุด)
            $historyTickets = Ticket::with('creator')
                ->where('assigned_to', $user->id)
                ->latest()
                ->take(6)
                ->get();

        } else {
            // ถ้าเป็น end-user (ไม่ใช่ admin / staff) ให้เด้งไปหน้ารายการ Ticket แทน (ไม่มี Dashboard พิเศษ)
            return redirect()->route('tickets.index');
        }

        // ปุ่มลัด (Quick Actions) ด้านบนของ Dashboard
        $quickActions = collect([
            [
                'icon' => 'MU',
                'label' => 'Manage Users',           // เมนูจัดการผู้ใช้
                'url' => route('users.index'),
                'visible' => $user->isAdmin(),       // แสดงเฉพาะ admin
            ],
            [
                'icon' => '+',
                'label' => 'Create Ticket',          // ปุ่มสร้าง ticket ใหม่
                'url' => route('tickets.create'),
                'visible' => !$user->isAdmin(),      // แสดงให้ user / staff (admin ไม่เห็น)
            ],
            [
                'icon' => 'TK',
                'label' => 'My Tickets',             // ไปหน้าดู ticket ของตัวเอง
                'url' => route('tickets.index'),
                'visible' => true,                   // ทุกคนเห็นได้
            ],
        ])->where('visible', true)->values()->all(); // filter เอาเฉพาะรายการที่ visible = true

        // ส่งตัวแปรต่าง ๆ ไปให้ view screens.dashboard
        return view('screens.dashboard', [
            'activePage'      => 'dashboard',             // ใช้สำหรับไฮไลท์เมนูที่ active
            'welcomeName'     => $welcomeName,            // ชื่อที่ใช้ทักใน Dashboard
            'stats'           => $stats,                  // กล่องสถิติ
            'quickActions'    => $quickActions,           // ปุ่มลัด
            'ticketStatus'    => $statusForChart,         // ข้อมูลกราฟสถานะ Ticket
            'historyTickets'  => $historyTickets ?? null, // ประวัติ ticket ล่าสุด (มีเฉพาะ staff)
            'priorityLabels'  => ['low' => 'Low', 'normal' => 'Medium', 'high' => 'High'],
            'priorityBacklog' => $metrics->backlogByPriority(), // จำนวน ticket ค้างงานแบ่งตาม priority
        ]);
    })->name('dashboard.admin'); // ตั้งชื่อ route เป็น dashboard.admin (ใช้กับ route() ใน blade ได้)

    // Route Kanban board เคยมีแต่ถูกลบออก (ตามคอมเมนต์)

    // สร้าง route แบบ resource สำหรับ Ticket (index, create, store, show, edit, update, destroy)
    Route::resource('tickets', TicketController::class);

    // กดอนุมัติ ticket (End user หรือ Admin ใช้ตามสิทธิ์ที่ controller กำหนด)
    Route::post('tickets/{ticket}/approve', [TicketController::class, 'approve'])
        ->name('tickets.approve');

    // กดปฏิเสธ ticket
    Route::post('tickets/{ticket}/reject', [TicketController::class, 'reject'])
        ->name('tickets.reject');

    // สร้าง comment ใหม่ใน ticket หนึ่ง ๆ
    Route::post('tickets/{ticket}/comments', [TicketCommentController::class, 'store'])
        ->name('tickets.comments.store');

    // แก้ไข comment เดิม
    Route::put('tickets/{ticket}/comments/{comment}', [TicketCommentController::class, 'update'])
        ->name('tickets.comments.update');

    // หน้าแสดงรายการ Notification ของผู้ใช้ปัจจุบัน
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');

    // หน้าเปลี่ยนรหัสผ่านของบัญชีตัวเอง (แสดงฟอร์ม)
    Route::get('/account/password', [AccountController::class, 'editPassword'])
        ->name('account.password.edit');

    // ส่งฟอร์มเปลี่ยนรหัสผ่าน (อัปเดต password)
    Route::post('/account/password', [AccountController::class, 'updatePassword'])
        ->name('account.password.update');

    // กลุ่ม route ที่ใช้ middleware 'auth.admin' (เฉพาะ admin เท่านั้น)
    Route::middleware('auth.admin')->group(function () {
        // route จัดการ user แบบ resource (index, create, store, edit, update, destroy ฯลฯ)
        Route::resource('users', AdminUserController::class);

        // เปลี่ยนสถานะ user ให้เป็น active
        Route::post('users/{user}/activate', [AdminUserController::class, 'activate'])
            ->name('users.activate');

        // เปลี่ยนสถานะ user ให้เป็น inactive / deactivate
        Route::post('users/{user}/deactivate', [AdminUserController::class, 'deactivate'])
            ->name('users.deactivate');

        // หน้า Admin สำหรับจัดการ Task (ดูรายการ ticket ที่เป็นงานของ staff)
        Route::get('/tasks/manage', [AdminTaskController::class, 'index'])
            ->name('admin.tasks.index');

        // Assign ticket ให้ staff คนใดคนหนึ่ง (Admin เป็นคนมอบหมาย)
        Route::post('/tasks/{ticket}/assign', [AdminTaskController::class, 'assign'])
            ->name('admin.tasks.assign');
    });
});
