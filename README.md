# NilaiKu Backend

Backend ini menggunakan PHP murni dan PostgreSQL.

## Menjalankan Lokal

```bash
docker compose down -v
docker compose up --build
```

API tersedia pada `http://localhost:8000/api`.

Endpoint `GET /api/health` dapat digunakan untuk memastikan API dan koneksi database aktif.

## Deployment VPS

Konfigurasi produksi memakai Nginx, PHP-FPM, dan PostgreSQL melalui Docker Compose tanpa mengekspos PostgreSQL atau PHP-FPM ke internet. Lihat [panduan deployment VPS](docs/deployment-vps.md).

## Akun Development

| Role | Email | Password |
| --- | --- | --- |
| Admin | admin@nilaiku.test | Password123! |
| Lecturer | dosen@nilaiku.test | Password123! |
| Student | budi.santoso@students.kampus.ac.id | Password123! |

## Endpoint Auth

| Method | Endpoint |
| --- | --- |
| POST | /api/auth/login |
| GET | /api/auth/me |
| POST | /api/auth/logout |

`POST /api/auth/login` menerima JSON `email`, `password`, dan `remember` opsional. Endpoint `me` dan `logout` memerlukan header `Authorization: Bearer <access_token>`.

`POST /api/auth/forgot-password` menerima `email` dan mengirim tautan reset melalui Resend. `POST /api/auth/reset-password` menerima `token` dan `password` baru. Salin `.env.example` menjadi `.env`, lalu isi `RESEND_API_KEY`, `RESEND_FROM`, dan `APP_FRONTEND_URL`. Alamat pengirim harus memakai domain yang sudah diverifikasi di Resend.

Untuk database Docker yang sudah dibuat sebelum fitur ini, jalankan `docker compose exec -T database psql -U nilaiku -d nilaiku < database/migrations/001_password_reset_tokens.sql`.

## Avatar Supabase Storage

| Method | Endpoint | Role |
| --- | --- | --- |
| POST | /api/profile/avatar | Student, Lecturer |
| DELETE | /api/profile/avatar | Student, Lecturer |

Unggah avatar menggunakan `multipart/form-data` dengan field `avatar`. Berkas yang diterima adalah JPEG, PNG, atau WebP dengan ukuran maksimal 1 MB. Buat bucket public `avatars` pada Supabase Storage, lalu isi `SUPABASE_URL`, `SUPABASE_SERVICE_ROLE_KEY`, dan `SUPABASE_STORAGE_BUCKET` dalam `.env`. Jangan gunakan service role key di frontend.

## Endpoint Master Akademik

| Method | Endpoint | Role |
| --- | --- | --- |
| GET | /api/academic-terms/active | Semua user terautentikasi |
| GET, POST | /api/academic-terms | Admin |
| PATCH | /api/academic-terms/{id} | Admin |
| GET, POST | /api/courses | Semua user, Admin untuk POST |
| PATCH | /api/courses/{id} | Admin |
| GET, POST | /api/classes | Admin dan Lecturer untuk GET, Admin untuk POST |
| GET, PATCH | /api/classes/{id} | Admin dan Lecturer untuk GET, Admin untuk PATCH |
| POST | /api/classes/{id}/close | Admin |
| GET | /api/schedules | Admin dan Lecturer |
| PUT | /api/classes/{id}/schedules | Admin |

Kelas menerima `term_id`, `course_id`, `lecturer_id`, `code`, `capacity`, serta `schedules`. Setiap slot jadwal berisi `day_of_week` 1–6, `start_time`, `end_time`, dan `room`.

## Endpoint Enrollment

| Method | Endpoint | Role |
| --- | --- | --- |
| GET, POST | /api/enrollments | Admin |
| POST | /api/enrollments/{id}/cancel | Admin |
| GET | /api/enrollments/me | Student |
| GET | /api/schedules/me | Student |

`POST /api/enrollments` menerima JSON `student_id` dan `class_id`. Enrollment baru hanya dapat dibuat untuk kelas aktif pada semester aktif.

## Endpoint Nilai

| Method | Endpoint | Role |
| --- | --- | --- |
| GET | /api/classes/{id}/grades | Lecturer |
| PUT | /api/grades/enrollments/{id} | Lecturer |
| POST | /api/grades/{id}/submit | Lecturer |
| GET | /api/grades | Admin |
| POST | /api/grades/{id}/verify | Admin |
| POST | /api/grades/{id}/return | Admin |
| POST | /api/grades/{id}/publish | Admin |
| GET | /api/grades/me | Student |

Simpan nilai draf menerima `assignment_score`, `midterm_score`, dan `final_exam_score`. Nilai akhir dihitung dengan bobot 30%, 30%, dan 40%.

## Endpoint Presensi

| Method | Endpoint | Role |
| --- | --- | --- |
| GET | /api/classes/{id}/attendance | Admin, Lecturer |
| GET | /api/attendance-meetings/{id} | Admin, Lecturer |
| POST | /api/classes/{id}/attendance-meetings | Lecturer |
| PUT | /api/attendance-meetings/{id}/records/{enrollmentId} | Lecturer |
| GET | /api/attendance | Admin |
| GET | /api/attendance/me | Student |

Meeting menerima `meeting_date` dan `topic`. Record presensi menerima `status`: `Hadir`, `Terlambat`, `Izin`, atau `Alpha`.
