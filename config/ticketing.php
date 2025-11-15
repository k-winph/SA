<?php

// ไฟล์ config หลักสำหรับตั้งค่าระบบ IT Support Ticket System
// ใช้กำหนดช่องทาง, สถานะ, SLA, กลุ่มงาน, และการแจ้งเตือนต่าง ๆ ของ ticket

return [
    // ช่องทางที่ ticket สามารถเข้ามาได้
    'channels' => [
        'portal' => 'User Portal',          // ผู้ใช้สร้างผ่านหน้าเว็บพอร์ทัล
        'email' => 'Email',                 // มาจากอีเมล
        'phone' => 'Phone',                 // รับแจ้งทางโทรศัพท์ แล้ว staff บันทึกให้
        'chat' => 'Chat / Messaging',       // มาจากแชท / ระบบ messaging
    ],

    // ตัวเลือก Impact (ระดับผลกระทบ) พร้อมคำอธิบาย
    'impacts' => [
        'low' => 'Low – localized impact or workaround available',         // กระทบเล็กน้อย มีวิธีแก้ชั่วคราวได้
        'medium' => 'Medium – department-level disruption',               // กระทบระดับแผนก
        'high' => 'High – company-wide outage or security incident',      // กระทบทั้งองค์กร / เหตุด้านความปลอดภัย
    ],

    // ตัวเลือก Urgency (ระดับความเร่งด่วน) พร้อมคำอธิบาย
    'urgencies' => [
        'low' => 'Low – informational or request',       // ขอข้อมูล / Request ทั่วไป
        'medium' => 'Medium – standard incident',        // เหตุขัดข้องปกติ
        'high' => 'High – time-sensitive disruption',    // กระทบงานด่วน ต้องรีบแก้
    ],

    // กำหนดสถานะต่าง ๆ ของ Ticket และการแสดงผล (label, สี, คอลัมน์ใน Kanban)
    'statuses' => [
        'open' => [
            'label' => 'Open',             // เปิดใหม่ ยังไม่ได้เริ่มทำ
            'color' => '#0f766e',          // สีสำหรับใช้ใน UI
            'kanban_column' => 'to_do',    // คอลัมน์บน Kanban board
        ],
        'in_progress' => [
            'label' => 'In Progress',      // กำลังดำเนินการ
            'color' => '#0369a1',
            'kanban_column' => 'in_progress',
        ],
        'waiting' => [
            'label' => 'On Hold',          // รอข้อมูล / รอผู้ใช้ / รอ vendor
            'color' => '#f97316',
            'kanban_column' => 'waiting',
        ],
        'testing' => [
            'label' => 'Testing',          // ทดสอบผลการแก้ไข
            'color' => '#6366f1',
            'kanban_column' => 'testing',
        ],
        'resolved' => [
            'label' => 'Resolved',         // แก้ไขแล้ว รอผู้ใช้ยืนยัน / รอปิดงาน
            'color' => '#facc15',
            'kanban_column' => 'resolved',
        ],
        'closed' => [
            'label' => 'Closed',           // ปิดงานสมบูรณ์
            'color' => '#f43f5e',
            'kanban_column' => 'closed',
        ],
    ],

    // สถานะเริ่มต้นของ ticket เวลาเปิดใหม่
    'default_status' => 'open',

    // การตั้งค่า SLA แยกตาม priority (ใช้ใน TicketAutomationService::determineSlaWindows)
    'sla' => [
        'low' => [
            'response_minutes' => 90,   // เวลาที่ควรตอบกลับครั้งแรก (นาที)
            'resolution_minutes' => 1440, // เวลาที่ควรแก้ให้จบ (นาที)
        ],
        'normal' => [
            'response_minutes' => 60,
            'resolution_minutes' => 480,
        ],
        'high' => [
            'response_minutes' => 30,
            'resolution_minutes' => 240,
        ],
    ],

    // กลุ่มงานที่รับผิดชอบ ticket แต่ละประเภท (ใช้ assignment_group)
    'assignment_groups' => [
        'network' => 'Network Operations',   // ทีมเน็ตเวิร์ก
        'hardware' => 'Hardware Desk',       // ทีมฮาร์ดแวร์
        'software' => 'Applications Squad',  // ทีมซอฟต์แวร์ / แอปฯ
        'access' => 'Identity & Access',     // ทีมจัดการบัญชี / สิทธิ์เข้าใช้
        'other' => 'Service Desk',           // งานอื่น ๆ รวมศูนย์ที่ Service Desk
    ],

    // การตั้งค่าการแจ้งเตือนเกี่ยวกับ ticket ใหม่
    'notifications' => [
        // เลือกว่าจะถือว่า "event หลัก" ของ ticket ใหม่คืออะไร
        // created = แจ้งเตือนตอนสร้าง ticket
        // assignment = แจ้งเตือนตอนมีการ assign ให้คนรับผิดชอบ
        'new_ticket_event' => env('TICKET_NEW_TICKET_EVENT', 'created'),

        // ถ้าตั้งเป็น true = เวลาแจ้งเตือน admin เกี่ยวกับ ticket ใหม่ 
        // จะไม่ส่ง Notification ไปให้ assignee (คนที่ถูก assign) อีกซ้ำหนึ่ง
        'exclude_assignee_from_new_ticket_event' => env('TICKET_EXCLUDE_ASSIGNEE_FROM_NEW_TICKET_EVENT', false),
    ],

    // การตั้งค่าการ ingest ticket จากระบบภายนอก (API / integration)
    'ingestion' => [
        // Token ที่ใช้ตรวจสอบว่า request จากภายนอกมีสิทธิ์ส่ง ticket เข้ามาได้หรือไม่
        // ใช้ร่วมกับ TicketIngestionRequest::authorize()
        'token' => env('TICKET_INGESTION_TOKEN', null),
    ],
];
