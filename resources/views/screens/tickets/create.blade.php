@extends('layouts.app') {{-- ใช้ layout หลักของระบบ --}}

@section('title', 'Create New Ticket') {{-- ตั้ง title หน้าเป็น "Create New Ticket" --}}

@section('content')
    @php
        // กำหนดค่า default ของ priority ถ้าเคย submit แล้ว error จะใช้ค่าจาก old()
        // ถ้าไม่มีก็ใช้ 'normal' เป็นค่าเริ่มต้น
        $defaultPriority = old('priority', 'normal');

        // ถ้าในอนาคตมี field channel จะใช้ค่าจาก old() เช่นกัน (ตอนนี้ยังไม่ได้ใช้ในฟอร์ม)
        $defaultChannel = old('channel', 'portal');
    @endphp

    {{-- ส่วนหัวของหน้า --}}
    <div class="page-header">
        <h1 class="page-title">Create New Ticket</h1>
    </div>

    {{-- การ์ดฟอร์มสร้าง Ticket ใหม่ --}}
    <section class="card form-card">
        {{-- ฟอร์มส่งไปที่ tickets.store เพื่อบันทึก ticket ใหม่ --}}
        <form action="{{ route('tickets.store') }}" method="post" enctype="multipart/form-data">
            @csrf {{-- token ป้องกัน CSRF --}}

            {{-- ช่องกรอกหัวข้อปัญหา (จำเป็นต้องกรอก) --}}
            <div class="form-group">
                <label for="subject">
                    Subject<span class="required">*</span>
                </label>
                <input
                    id="subject"
                    name="subject"
                    type="text"
                    placeholder="Enter Subject"
                    value="{{ old('subject') }}" {{-- ถ้า validate ไม่ผ่านจะมีค่าเดิมกลับมา --}}
                >
            </div>

            {{-- ช่องกรอกรายละเอียดปัญหา (จำเป็นต้องกรอก) --}}
            <div class="form-group">
                <label for="description">
                    Description<span class="required">*</span>
                </label>
                <textarea
                    id="description"
                    name="description"
                    placeholder="Enter Description"
                >{{ old('description') }}</textarea>
            </div>

            {{-- เลือกระดับความสำคัญ (Priority) แบบ radio --}}
            <div class="grid-two">
                <div class="form-group priority-group">
                    <label>
                        Priority<span class="required">*</span>
                    </label>
                    <div class="priority-options">
                        @foreach ($priorityOptions as $value => $label)
                            <label class="priority-option">
                                <input
                                    type="radio"
                                    name="priority"
                                    value="{{ $value }}"
                                    {{-- เช็คให้ติ๊กตัวที่ตรงกับ defaultPriority --}}
                                    @checked($defaultPriority === $value)
                                >
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- เลือก Category ของ Ticket --}}
            {{-- ตอนนี้ if/else สองฝั่งเหมือนกันทั้งคู่ (admin กับ user ใช้ฟิลด์เดียวกัน) --}}
            @if (auth()->user()?->isAdmin())
                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="">Select Category</option>
                        @foreach ($categories as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected(old('category') === $value)
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @else
                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="">Select Category</option>
                        @foreach ($categories as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected(old('category') === $value)
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- อัปโหลดไฟล์แนบ (รูป/เอกสาร) --}}
            <div class="form-group">
                <label for="attachment">Attachment</label>
                <input
                    id="attachment"
                    name="attachment"
                    type="file"
                    accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
                >
                <small>
                    Up to 20 MB. Automatically virus scanned and logged.
                    {{-- คำอธิบาย: จำกัดขนาดไฟล์ และระบบจะสแกนไวรัส + log ให้โดยอัตโนมัติ (เป็นข้อความ UI) --}}
                </small>
            </div>

            {{-- ปุ่มด้านล่างฟอร์ม --}}
            <div class="form-actions">
                {{-- ปุ่มยกเลิก กลับไปหน้ารายการ Ticket --}}
                <a class="btn btn-cancel" href="{{ route('tickets.index') }}">Cancel</a>

                {{-- ปุ่มส่งฟอร์มสร้าง Ticket --}}
                <button class="btn btn-submit" type="submit">Submit</button>
            </div>
        </form>
    </section>

@endsection
