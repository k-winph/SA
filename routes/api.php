<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

// กำหนด console command ชื่อว่า "inspire"
// เวลาเรียกในเทอร์มินัล: php artisan inspire
Artisan::command('inspire', function () {
    // แสดงข้อความ quote สร้างแรงบันดาลใจ 1 บรรทัดใน console
    $this->comment(Inspiring::quote());
})
// คำอธิบายสั้น ๆ ของคำสั่งนี้ (ไว้ให้คนอ่านรู้ว่าทำอะไร)
->purpose('Display an inspiring quote');