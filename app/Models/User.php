<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    // กำหนดค่าคงที่ (role) ของผู้ใช้แต่ละประเภท
    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';
    public const ROLE_USER = 'user';

    /** @use HasFactory<\Database\Factories\UserFactory> */
    // ใช้ factory (สำหรับ seeding / ทดสอบ) + ระบบ Notification ของ Laravel
    use HasFactory, Notifiable;

    /**
     * ฟิลด์ที่อนุญาตให้กรอกแบบ mass assignable (create(), update(), fill())
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * ฟิลด์ที่ต้องซ่อนเวลาแปลงเป็น array / JSON (เช่นคืนค่า API)
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * กำหนด cast ของฟิลด์ต่าง ๆ (เวลาอ่าน/เขียนค่าจาก DB)
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime', // เวลายืนยันอีเมล แปลงเป็น Carbon
            'password' => 'hashed',           // password จะถูก hash อัตโนมัติเมื่อเซตค่า
            'is_active' => 'boolean',         // แปลง is_active เป็น bool
            'deactivated_at' => 'datetime',   // เวลาโดน deactivate
        ];
    }

    // ความสัมพันธ์: user สร้าง ticket อะไรบ้าง (เชื่อมผ่าน created_by)
    public function ticketsCreated()
    {
        return $this->hasMany(Ticket::class, 'created_by');
    }

    // ความสัมพันธ์: ticket ไหนบ้างที่ assign ให้ user คนนี้ (assigned_to)
    public function ticketsAssigned()
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    // helper: เช็คว่า user เป็น admin ไหม
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    // helper: เช็คว่า user เป็น staff ไหม
    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    // helper: ใช้คำว่า "agent" แทน staff (ในระบบ ticket)
    public function isAgent(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    // helper: เช็คว่า user เป็น end user (ผู้ใช้ทั่วไป) ไหม
    public function isEndUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    // เช็ค role เดียวว่า user มี role ตรงตามที่ถามไหม
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * @param  list<string>  $roles
     */
    // เช็คว่า user มี role ใด role หนึ่งในลิสต์ที่ส่งมาไหม
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    // scope: query เฉพาะ user ที่ active อยู่
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // helper: คืนค่า bool ว่าตอนนี้ user active อยู่ไหม
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    // ทำให้ user กลับมา active อีกครั้ง + ล้าง deactivated_at
    public function activate(): void
    {
        $this->forceFill([
            'is_active' => true,
            'deactivated_at' => null,
        ])->save();
    }

    // ปิดการใช้งาน user (inactive) พร้อมบันทึกเวลา deactivated_at
    public function deactivate(): void
    {
        $this->forceFill([
            'is_active' => false,
            'deactivated_at' => now(),
        ])->save();
    }
}
