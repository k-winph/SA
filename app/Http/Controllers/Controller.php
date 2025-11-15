<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

// Controller หลักของระบบทุกตัวจะ extends มาจากคลาสนี้
abstract class Controller extends BaseController
{
    // ใช้ trait สำหรับตรวจสิทธิ์ (authorization), จัดการ job, และ validate request
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
}
