<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // แสดงรายการผู้ใช้ทั้งหมด (หน้า manage users)
    public function index()
    {
        // ดึง user ทั้งหมดมาเรียงตามชื่อ แล้วแบ่งหน้า หน้าละ 10 คน
        $users = User::orderBy('name')->paginate(10);

        // ส่งข้อมูลไปยัง view แสดงรายชื่อ user
        return view('screens.users.index', [
            'users' => $users,
            'activePage' => 'users',
        ]);
    }

    // แสดงฟอร์มสร้างผู้ใช้ใหม่
    public function create()
    {
        return view('screens.users.create', ['activePage' => 'users']);
    }

    // แสดงรายละเอียดผู้ใช้รายบุคคล
    public function show(User $user)
    {
        return view('screens.users.show', [
            'user' => $user,
            'activePage' => 'users',
        ]);
    }

    // รับข้อมูลจากฟอร์มสร้างผู้ใช้ใหม่ แล้วบันทึกลงฐานข้อมูล
    public function store(Request $request)
    {
        // validate ข้อมูลที่ส่งมาจากฟอร์ม
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_STAFF, User::ROLE_USER])],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        // ถ้าไม่ได้ติ๊ก is_active ให้ถือว่า active เป็น true (ค่า default)
        $isActive = $request->boolean('is_active', true);

        // สร้าง user ใหม่ในฐานข้อมูล
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => $isActive,
            'password' => Hash::make($validated['password']), // เข้ารหัส password ก่อนเก็บ
        ]);

        // ถ้าตั้งค่าให้ไม่ active ให้เรียก method deactivate() เพื่อจัดการฟิลด์อื่น ๆ ด้วย
        if (!$isActive) {
            $user->deactivate();
        }

        // กลับไปหน้า list ผู้ใช้ พร้อมข้อความสำเร็จ
        return redirect()->route('users.index')->with('status', 'User created successfully.');
    }

    // แสดงฟอร์มแก้ไขข้อมูลผู้ใช้
    public function edit(User $user)
    {
        return view('screens.users.edit', [
            'user' => $user,
            'activePage' => 'users',
        ]);
    }

    // อัปเดตข้อมูลผู้ใช้ที่ถูกแก้ไข
    public function update(Request $request, User $user)
    {
        // validate ข้อมูลที่ส่งมาจากฟอร์มแก้ไข
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_STAFF, User::ROLE_USER])],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'min:8', 'confirmed'],
        ]);

        // ห้ามให้ user ปิด active ตัวเอง (ถ้าเป็น account ปัจจุบัน)
        $isActive = $request->user()->id === $user->id
            ? $user->is_active
            : $request->boolean('is_active', true);

        // fill ข้อมูลใหม่ลงใน model แต่ยังไม่ save
        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => $isActive,
        ]);

        // ถ้ามีกรอกรหัสผ่านใหม่เข้ามา ให้เปลี่ยน password
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        // ถ้ามีการเปลี่ยนค่า is_active ให้จัดการ deactivated_at ให้สอดคล้องกัน
        if ($user->isDirty('is_active')) {
            $user->deactivated_at = $isActive ? null : now();
        }

        // บันทึกลงฐานข้อมูล
        $user->save();

        return redirect()->route('users.index')->with('status', 'User updated successfully.');
    }

    // ลบ user ออกจากระบบ
    public function destroy(User $user)
    {
        // เช็คก่อนว่าเป็นการลบ account ตัวเองหรือเปล่า ถ้าใช่ให้ block
        if ($response = $this->preventSelfAction($user)) {
            return $response;
        }

        // ถ้า user นี้เป็น admin และเป็น admin คนสุดท้าย ห้ามลบ
        if ($user->isAdmin() && User::where('role', User::ROLE_ADMIN)->count() === 1) {
            return back()->withErrors(['user' => 'Cannot delete the last admin account.']);
        }

        // ลบ user
        $user->delete();

        return redirect()->route('users.index')->with('status', 'User deleted.');
    }

    // เปิดใช้งาน user (reactivate)
    public function activate(User $user)
    {
        // กันไม่ให้ทำ action กับ account ตัวเอง
        if ($response = $this->preventSelfAction($user)) {
            return $response;
        }

        // ถ้า user นี้ active อยู่แล้ว ไม่ต้องทำอะไร
        if ($user->isActive()) {
            return back()->with('status', "{$user->name} is already active.");
        }

        // เรียก method activate() ใน model เพื่อเปิดใช้งาน
        $user->activate();

        return back()->with('status', "{$user->name} reactivated successfully.");
    }

    // ปิดการใช้งาน user (deactivate)
    public function deactivate(User $user)
    {
        // กันไม่ให้ทำ action กับ account ตัวเอง
        if ($response = $this->preventSelfAction($user)) {
            return $response;
        }

        // ถ้า user นี้เป็น admin และเป็น admin คนสุดท้ายที่ยัง active อยู่ ห้าม deactivate
        if ($user->isAdmin() && User::where('role', User::ROLE_ADMIN)->where('is_active', true)->count() === 1) {
            return back()->withErrors(['user' => 'Cannot deactivate the last active admin.']);
        }

        // ถ้า user นี้ inactive อยู่แล้ว ไม่ต้องทำอะไร
        if (!$user->isActive()) {
            return back()->with('status', "{$user->name} is already inactive.");
        }

        // เรียก method deactivate() ใน model เพื่อปิดใช้งาน
        $user->deactivate();

        return back()->with('status', "{$user->name} deactivated successfully.");
    }

    // ฟังก์ชันช่วยเช็คว่า action นี้กำลังจะทำกับ account ตัวเองรึเปล่า
    // ถ้าใช่จะ return redirect response กลับไปพร้อม error
    protected function preventSelfAction(User $user): ?RedirectResponse
    {
        // เช็คว่า id ของ user ที่ login ตรงกับ user ที่กำลังจัดการอยู่หรือไม่
        if (auth()->id() === $user->id) {
            return back()->withErrors(['user' => 'You cannot perform this action on your own account.']);
        }

        // ถ้าไม่ใช่ตัวเอง ให้ return null เพื่อให้ทำงานต่อได้
        return null;
    }
}
