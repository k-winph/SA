<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketIngestionRequest extends FormRequest
{
    // ตรวจสอบว่า request นี้ "ได้รับอนุญาต" ให้สร้าง ticket ผ่าน ingestion หรือไม่
    public function authorize(): bool
    {
        // ดึง token ที่ตั้งค่าไว้ใน config/ticketing.php (ส่วน ingestion.token)
        $token = config('ticketing.ingestion.token');

        // ถ้าไม่ได้ตั้งค่า token เลย แปลว่าเปิดให้ทุกคนเรียกใช้ได้ (ไม่เช็ค header)
        if (!$token) {
            return true;
        }

        // ถ้ามี token ให้เปรียบเทียบกับ header X-Integration-Token ที่ส่งมาจาก client
        // ใช้ hash_equals เพื่อป้องกัน timing attack
        return hash_equals($token, (string) $this->header('X-Integration-Token'));
    }

    // กำหนดกฎการ validate field ต่าง ๆ ของการ ingest ticket ผ่าน API
    public function rules(): array
    {
        // ดึง channel ที่อนุญาตจาก config (ticketing.channels) มาเป็น list
        $channels = array_keys(config('ticketing.channels', []));

        return [
            // หัวข้อปัญหา: ต้องกรอก, เป็น string, ยาวไม่เกิน 255 ตัวอักษร
            'subject' => ['required', 'string', 'max:255'],

            // รายละเอียดปัญหา: ต้องกรอก, เป็น string
            'description' => ['required', 'string'],

            // ช่องทางที่ ticket ถูกส่งเข้ามา (เช่น email, api, portal ฯลฯ)
            // ต้องเป็นค่าที่อยู่ใน list $channels เท่านั้น
            'channel' => ['required', Rule::in($channels)],

            // priority, impact, urgency สามารถไม่กรอกได้ แต่ถ้ากรอกต้องเป็นค่าที่กำหนดไว้
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high'])],
            'impact' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'urgency' => ['nullable', Rule::in(['low', 'medium', 'high'])],

            // category เป็นหมวดหมู่ของ ticket จากระบบต้นทาง (string ธรรมดา)
            'category' => ['nullable', 'string', 'max:255'],

            // ข้อมูลผู้ร้องขอ (requester) – ใช้สร้าง/ผูก user ถ้าไม่ได้ login
            'requester_email' => ['nullable', 'email'],
            'requester_name' => ['nullable', 'string', 'max:255'],
            'requester_contact' => ['nullable', 'string', 'max:255'],

            // reference ของระบบภายนอก เช่น ID จาก chatbot, ระบบ HR ฯลฯ
            'ingestion_reference' => ['nullable', 'string', 'max:255'],

            // metadata: รับเป็น array เพื่อเก็บข้อมูลเสริมอื่น ๆ ตามระบบภายนอกส่งมา
            'metadata' => ['nullable', 'array'],
        ];
    }

    // ข้อความ error แบบ custom สำหรับ field ต่าง ๆ
    public function messages(): array
    {
        return [
            // ถ้า channel ไม่อยู่ใน list ที่ config ไว้ ให้ขึ้นข้อความนี้
            'channel.in' => 'Channel must be one of the configured ingestion channels.',
        ];
    }
}
