# IT Support Ticket System

ระบบ IT Support Ticket System สำหรับจัดการคำร้องขอซ่อม/แก้ปัญหาด้าน IT ภายในองค์กร  
รองรับการใช้งานหลายบทบาท (Admin / Staff / End-User) พร้อมฟีเจอร์ SLA, Comment, Notification และ Dashboard พื้นฐาน

โปรเจกต์นี้พัฒนาโดยใช้ **Laravel + MySQL + Redis + Nginx** และรันผ่าน **Docker Compose** เป็นหลัก

---

## 1. Feature หลักของระบบ

- 👤 **User & Role**
  - แบ่งบทบาทเป็น `admin`, `staff`, `user`
  - Admin จัดการผู้ใช้งานได้ (สร้าง/แก้ไข/ลบ/เปิด-ปิดการใช้งาน)
  - ปิดการใช้งาน (deactivate) user ได้ โดยระบบบังคับไม่ให้ลบ/ปิด admin คนสุดท้าย

- 🎫 **Ticket Management**
  - ผู้ใช้ทั่วไป (`user`) สร้าง ticket, ดูรายละเอียด, ดู timeline, comment, approve/reject งานได้
  - Staff (`staff`) รับงาน, เปลี่ยนสถานะ ticket, comment, แนบไฟล์, จัดการ priority/impact/category ได้
  - รองรับสถานะหลัก: `open`, `in_progress`, `waiting`, `testing`, `resolved`, `closed`

- 💬 **Comment & Notification**
  - ทุกการ comment และเปลี่ยนสถานะจะถูก log เป็น `TicketStatusHistory`
  - มี Notification เก็บในฐานข้อมูล (database notifications) และแสดงหน้า Notification Center
  - แจ้งเตือน Admin/เจ้าของ ticket/ผู้รับผิดชอบ ตามสถานการณ์

---

## 2. Tech Stack

- **Backend**: Laravel (PHP)
- **Database**: MySQL
- **Cache / Queue**: Redis + Laravel Queue Worker
- **Web Server**: Nginx
- **Frontend**: Blade + Tailwind CSS + Vite
- **Containerization**: Docker & Docker Compose

---

## 3. Service / Container Overview

จากไฟล์ `docker-compose.yml` ระบบหลักจะมี service ดังนี้

- `app`  
  - PHP-FPM + Laravel Application
  - ติดตั้ง dependency ผ่าน Composer, รันโค้ด Laravel ทั้งหมด

- `web`  
  - Nginx ทำหน้าที่เป็น web server reverse proxy มายัง `app`
  - เปิดพอร์ตบนเครื่อง host (เช่น `8085` → 80 ใน container)

- `mysql`  
  - MySQL Database สำหรับเก็บข้อมูลระบบ
  - มีการแมปพอร์ตจาก host (เช่น `3310` → 3306)

- `redis`  
  - ใช้สำหรับ Queue / Cache

- `queue`  
  - Container ที่รัน `php artisan queue:work` เพื่อประมวลผลงานในคิว (เช่น notification)

- `phpmyadmin`  
  - UI สำหรับจัดการ MySQL ผ่าน browser  
  - เปิดพอร์ตบน host (เช่น `8082` → 80)

> พอร์ตจริงที่ใช้ดูได้จากคำสั่ง  
> `docker compose ps`

---

## 4. การติดตั้งและรันระบบด้วย Docker (แนะนำ)

### 4.1 เตรียมเครื่อง

ต้องมีโปรแกรมต่อไปนี้ติดตั้งในเครื่อง

- [Docker](https://www.docker.com/)
- [Docker Compose](https://docs.docker.com/compose/) (ปกติมากับ Docker Desktop แล้ว)
- Git (ถ้ายังไม่ได้ clone โปรเจกต์)

### 4.2 Clone โปรเจกต์

```bash
git clone https://github.com/k-winph/SA.git
cd SA
```

> ถ้าโฟลเดอร์โปรเจกต์จริงของคุณอยู่ลึกกว่านี้ (เช่น `sa-project/sa`) ให้ cd เข้าไปให้ตรงกับที่มีไฟล์ `docker-compose.yml` และโค้ด Laravel

### 4.3 สร้างไฟล์ `.env`

คัดลอกจากไฟล์ตัวอย่าง

```bash
cp .env.example .env
```

จากนั้นเปิดไฟล์ `.env` แล้วตรวจสอบ/แก้ค่าที่สำคัญ เช่น

```env
APP_NAME="IT Support Ticket System"
APP_ENV=local
APP_KEY=          # จะใช้คำสั่ง generate ในภายหลัง
APP_DEBUG=true
APP_URL=http://localhost:8085   # ให้ตรงกับพอร์ต Nginx

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=sa                    # ให้ตรงกับ MYSQL_DATABASE ใน docker-compose.yml
DB_USERNAME=appuser               # ให้ตรงกับ MYSQL_USER
DB_PASSWORD=apppass               # ให้ตรงกับ MYSQL_PASSWORD

QUEUE_CONNECTION=database
CACHE_DRIVER=file
SESSION_DRIVER=file

# Config ticketing
TICKET_NEW_TICKET_EVENT=created
TICKET_EXCLUDE_ASSIGNEE_FROM_NEW_TICKET_EVENT=false
TICKET_INGESTION_TOKEN=changeme-secret-token
```

> ดูค่า `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD` ที่ถูกตั้งไว้ใน `docker-compose.yml` แล้วตั้งค่าให้ตรงกันใน `.env`

### 4.4 สร้างและรัน Container

```bash
docker compose up -d --build
```

คำสั่งนี้จะ:

- build image ของ `app` ตาม `Dockerfile`
- สร้างและรัน container ทั้งหมด (`app`, `web`, `mysql`, `redis`, `queue`, `phpmyadmin`)

สามารถเช็คสถานะ container ได้ด้วย

```bash
docker compose ps
```

### 4.5 รัน migration + seeder

> ใช้ชื่อ service คือ `app` (เวลาใช้ `docker compose exec`)  
> หรือจะใช้ชื่อ container จริง เช่น `sa-app` กับ `docker exec` ก็ได้

```bash
# generate APP_KEY
docker compose exec app php artisan key:generate

# migrate ตารางทั้งหมด
docker compose exec app php artisan migrate --force

# seed ข้อมูลเริ่มต้น (admin + ticket ตัวอย่าง)
docker compose exec app php artisan db:seed --force
```

Seeder จะสร้างข้อมูลตัวอย่างดังนี้

- ผู้ใช้ระบบที่เป็น **Admin เริ่มต้น**
  - Email: `admin@example.com`
  - Password: `admin123456`

และสร้าง ticket ตัวอย่าง 1 อันจาก `TicketSeeder`

### 4.6 การเข้าใช้งานระบบ

- **เว็บหลัก (Nginx + Laravel)**  
  ไปที่: `http://localhost:8085`  
  หรือใช้พอร์ตที่กำหนดใน `docker-compose.yml` ส่วน service `web` เช่น:

  ```text
  0.0.0.0:8085->80/tcp
  ```

- **หน้า Login**  
  ใช้บัญชี admin จาก seeder

  - Email: `66160255@go.buu.ac.th`
  - Password: `Tan123456789`

- **phpMyAdmin**  
  ไปที่: `http://localhost:8082` (หรือพอร์ตที่แมปไว้ใน service `phpmyadmin`)  
  แล้วใช้ข้อมูลเชื่อมต่อจาก `docker-compose.yml` (เช่น user/password ของ MySQL)

---

## 5. การใช้งาน API Ingestion

ระบบมี endpoint สำหรับสร้าง ticket ผ่าน API เพื่อเชื่อมต่อกับระบบภายนอก เช่น email gateway, bot ฯลฯ

### Endpoint

```http
POST /api/ingest/tickets
Content-Type: application/json
X-Integration-Token: {TICKET_INGESTION_TOKEN ที่ตั้งใน .env}
```

### ตัวอย่าง Request

```json
{
  "subject": "VPN disconnected every hour",
  "description": "Tunnel drops on Wi-Fi and LTE.",
  "channel": "email",
  "category": "network",
  "impact": "high",
  "urgency": "medium",
  "requester_email": "user@example.com",
  "requester_name": "Remote User",
  "metadata": {
    "message_id": "<abc123@example.com>"
  }
}
```

### ตัวอย่าง Response

```json
{
  "ticket_id": 1,
  "status": "open",
  "assignment_group": "Network Operations",
  "priority": "high",
  "sla_due_at": "2025-11-16T10:00:00Z",
  "knowledge_base_suggestions": []
}
```

> ต้องตั้งค่า `TICKET_INGESTION_TOKEN` ใน `.env` ให้ตรงกับ header `X-Integration-Token` ก่อนใช้งาน endpoint นี้

---

## 6. คำสั่งที่ใช้บ่อยใน Container

```bash
# ดูสถานะ container ทั้งหมด
docker compose ps

# เข้า shell ของ app container
docker compose exec app bash

# รัน artisan ทั่วไป
docker compose exec app php artisan route:list
docker compose exec app php artisan migrate
docker compose exec app php artisan tinker

# ดู log Laravel
docker compose exec app tail -f storage/logs/laravel.log
```

---

## 7. Troubleshooting เบื้องต้น

- ถ้ารันแล้วเข้าเว็บไม่ได้
  - เช็ค `docker compose ps` ว่า service `web` และ `app` เป็น `Up` หรือไม่
  - เช็คว่าใช้พอร์ตถูกต้อง (เช่น `8085`)
  - เช็คว่าคุณเข้า `http://localhost:8085` ไม่ใช่พอร์ตอื่น

- ถ้า migrate ไม่ผ่าน
  - เช็คว่า `mysql` container ขึ้นแล้ว
  - เช็คค่าตระกูล `DB_*` ใน `.env` ให้ตรงกับ `docker-compose.yml`
  - ลอง `docker compose exec app php artisan config:clear`

- ถ้า seeder ยังไม่สร้าง admin
  - รัน `docker compose exec app php artisan db:seed --force` อีกรอบ
  - ตรวจสอบ class `DatabaseSeeder` ว่าเรียก `AdminUserSeeder::class` อยู่หรือไม่

---

## 8. License / Usage

โปรเจกต์นี้สร้างเพื่อใช้ในการเรียนวิชา System Analysis / Software Architecture  
ผู้ใช้งานสามารถนำโค้ดไปศึกษา ปรับใช้ และต่อยอดได้ตามความเหมาะสม
