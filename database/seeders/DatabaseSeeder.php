<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // seeder หลักของระบบ เวลาเราเรียกคำสั่ง php artisan db:seed
    // Laravel จะมารัน method run() ในคลาสนี้เป็นตัวแรก
    public function run(): void
    {
        // เรียก seeder ย่อยตามลำดับที่กำหนดใน array นี้
        // 1) AdminUserSeeder  -> สร้าง/อัปเดตบัญชี admin เริ่มต้น
        // 2) TicketSeeder     -> สร้าง ticket ตัวอย่าง (seed ข้อมูล sample)
        $this->call([
            AdminUserSeeder::class,
            TicketSeeder::class,
        ]);
    }
}
