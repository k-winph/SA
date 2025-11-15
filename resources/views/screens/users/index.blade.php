@extends('layouts.app') {{-- ใช้ layout หลักของระบบจากไฟล์ layouts/app.blade.php --}}

@section('title', 'Manage User') {{-- ตั้งชื่อหน้าใน <title> เป็น "Manage User" --}}

@section('content')
    {{-- ส่วนหัวของหน้า: ชื่อหน้า + ปุ่มสร้างผู้ใช้ใหม่ --}}
    <div class="page-header">
        <h1 class="page-title">Manage User</h1>
        {{-- ปุ่มไปหน้าแบบฟอร์มสร้างผู้ใช้ใหม่ --}}
        <a href="{{ route('users.create') }}" class="btn btn-primary">+ Create User</a>
    </div>

    {{-- ตารางแสดงรายการผู้ใช้ทั้งหมด --}}
    <table class="user-table">
        <thead>
            <tr>
                <th>Name</th>     {{-- ชื่อผู้ใช้ --}}
                <th>Email</th>    {{-- อีเมล --}}
                <th>Role</th>     {{-- สิทธิ์/บทบาท (admin, staff, user) --}}
                <th>Status</th>   {{-- สถานะการใช้งาน (Active / Inactive) --}}
                <th>Created</th>  {{-- วันที่สร้างบัญชี --}}
                <th>Actions</th>  {{-- ปุ่มแก้ไข/ปิดการใช้งาน/ลบ --}}
            </tr>
        </thead>
        <tbody>
            {{-- วนลูปแสดงผู้ใช้ ถ้าไม่มีเลยจะไปที่ @empty --}}
            @forelse ($users as $user)
                @php
                    // เช็คว่าผู้ใช้ในแถวนี้คือคนเดียวกับที่กำลังล็อกอินอยู่หรือไม่
                    // เพื่อไม่ให้ตัวเอง deactivate หรือ delete ตัวเองได้
                    $isSelf = auth()->id() === $user->id;
                @endphp
                <tr>
                    {{-- แสดงชื่อผู้ใช้ --}}
                    <td data-label="Name">{{ $user->name }}</td>

                    {{-- แสดงอีเมล --}}
                    <td data-label="Email">{{ $user->email }}</td>

                    {{-- แสดง Role พร้อม status-pill คนละสีสำหรับ admin กับ role อื่น ๆ --}}
                    <td data-label="Role">
                        <span class="status-pill {{ $user->role === 'admin' ? 'activate' : 'in-progress' }}">
                            {{ ucfirst($user->role) }}
                        </span>
                    </td>

                    {{-- แสดงสถานะ Active / Inactive --}}
                    <td data-label="Status">
                        <span class="status-pill {{ $user->is_active ? 'activate' : 'danger' }}">
                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                        </span>

                        {{-- ถ้า user นี้ไม่ active และมีวันที่ deactivated แสดงเวลาคร่าว ๆ เช่น "2 hours ago" --}}
                        @if (!$user->is_active && $user->deactivated_at)
                            <small class="text-muted" style="display:block;">
                                {{ $user->deactivated_at->diffForHumans() }}
                            </small>
                        @endif
                    </td>

                    {{-- แสดงวันที่สร้าง user ในรูปแบบ 01 Jan 2025 --}}
                    <td data-label="Created">{{ $user->created_at?->format('d M Y') }}</td>

                    {{-- คอลัมน์ Actions สำหรับปุ่มต่าง ๆ --}}
                    <td data-label="Actions">
                        <div class="table-actions">
                            {{-- ปุ่มไปหน้าแก้ไขข้อมูลผู้ใช้ --}}
                            <a class="btn edit" href="{{ route('users.edit', $user) }}">Edit</a>

                            {{-- ถ้าไม่ใช่ user คนเดียวกับที่ล็อกอินอยู่ ถึงจะให้ deactivate / delete ได้ --}}
                            @if (!$isSelf)
                                {{-- ถ้า user ยัง active แสดงปุ่ม Deactivate --}}
                                @if ($user->is_active)
                                    <form action="{{ route('users.deactivate', $user) }}"
                                          method="POST"
                                          onsubmit="return confirm('Deactivate this user account?');">
                                        @csrf
                                        <button class="btn warning" type="submit">Deactivate</button>
                                    </form>
                                @else
                                    {{-- ถ้า user inactive แล้ว แสดงปุ่ม Activate --}}
                                    <form action="{{ route('users.activate', $user) }}"
                                          method="POST"
                                          onsubmit="return confirm('Reactivate this user account?');">
                                        @csrf
                                        <button class="btn success" type="submit">Activate</button>
                                    </form>
                                @endif

                                {{-- ปุ่มลบผู้ใช้ (DELETE) พร้อมกล่อง confirm ก่อนส่งฟอร์ม --}}
                                <form action="{{ route('users.destroy', $user) }}"
                                      method="POST"
                                      onsubmit="return confirm('Delete this user?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn delete" type="submit">Delete</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                {{-- กรณีไม่มีผู้ใช้ในระบบเลย --}}
                <tr>
                    <td colspan="5">No users found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- แสดงปุ่มเปลี่ยนหน้า pagination --}}
    <div class="pagination-wrap">
        {{ $users->links() }}
    </div>
@endsection
