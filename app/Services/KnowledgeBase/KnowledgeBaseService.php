<?php

namespace App\Services\KnowledgeBase;

use App\Models\Ticket;
use Illuminate\Support\Str;

class KnowledgeBaseService
{
    // ดึงบทความทั้งหมดจาก config knowledge_base.php
    public function all(): array
    {
        // คืนค่า array ของบทความจากไฟล์ config('knowledge_base.articles')
        return config('knowledge_base.articles', []);
    }

    /**
     * แนะนำบทความจาก Knowledge Base ตามเนื้อหาของ ticket หรือ subject/description ที่ส่งมา
     *
     * @param  Ticket|string  $subjectOrTicket  รับได้ทั้ง Ticket model หรือ string ของหัวข้อ/subject
     * @param  string|null  $description        รายละเอียดเพิ่มเติม (ใช้เมื่อส่งเป็น string)
     * @param  int  $limit                      จำนวนบทความที่จะแนะนำสูงสุด
     */
    public function suggestForTicket(Ticket|string $subjectOrTicket, ?string $description = null, int $limit = 3): array
    {
        // ถ้าส่งเข้ามาเป็น Ticket model ให้ดึง subject และ description จาก ticket
        if ($subjectOrTicket instanceof Ticket) {
            $subject = $subjectOrTicket->subject;
            $description = $subjectOrTicket->description;
        } else {
            // ถ้าเป็น string ให้ใช้เป็น subject ตรง ๆ
            $subject = $subjectOrTicket;
        }

        // รวม subject + description แล้วแปลงเป็นตัวพิมพ์เล็ก เพื่อใช้ค้นหาแบบ case-insensitive
        $haystack = Str::lower(trim($subject . ' ' . ($description ?? '')));

        // เอาบทความทั้งหมดมาคำนวณคะแนนความเกี่ยวข้อง
        $scored = collect($this->all())
            ->map(function (array $article) use ($haystack) {
                $score = 0;

                // วนดู tag ของบทความแต่ละอัน ถ้า tag ปรากฏในข้อความ ticket ให้บวกคะแนนทีละ 2
                foreach ($article['tags'] as $tag) {
                    if (Str::contains($haystack, Str::lower($tag))) {
                        $score += 2;
                    }
                }

                // ถ้าชื่อบทความ (title) ปรากฏในข้อความ ticket ให้บวกเพิ่มอีก 1 คะแนน
                if (Str::contains($haystack, Str::lower($article['title']))) {
                    $score += 1;
                }

                // เพิ่ม key 'score' เข้าไปในข้อมูลบทความ เพื่อใช้เรียงลำดับทีหลัง
                return $article + ['score' => $score];
            })
            // กรองเอาเฉพาะบทความที่มี score > 0 (คือมีความเกี่ยวข้องบ้าง)
            ->filter(fn ($article) => $article['score'] > 0)
            // เรียงลำดับจาก score สูงไปต่ำ (บทความที่เกี่ยวข้องมากอยู่บนสุด)
            ->sortByDesc('score')
            // จำกัดจำนวนบทความตาม $limit (ค่า default = 3)
            ->take($limit)
            // รีเซ็ต index ให้เป็น 0,1,2,...
            ->values()
            // แปลงกลับเป็น array ธรรมดา
            ->all();

        // คืนค่าบทความที่คัดมาแล้ว
        return $scored;
    }
}
