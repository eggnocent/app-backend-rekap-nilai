# Struktur Backend NilaiKu

Proyek ini tetap memakai PHP murni tanpa framework. File requirement berada di root, sedangkan implementasi fitur dipisahkan agar tidak menumpuk dalam satu file.

```text
.
├── api/
│   └── index.php              # titik masuk API
├── features/                  # modul implementasi per fitur
│   ├── auth/
│   ├── dashboard/
│   ├── profile/
│   ├── students/
│   ├── lecturers/
│   ├── courses/
│   ├── classes/
│   ├── enrollments/
│   ├── grades/
│   ├── attendance/
│   ├── activities/
│   └── academic-events/
├── helpers/                   # response JSON, UUID, validator, utilitas
├── middleware/                # autentikasi dan cek role
├── uploads/
│   ├── avatars/
│   └── documents/
├── index.html                 # file requirement / halaman status
├── style.css                  # file requirement
├── script.js                  # file requirement
├── koneksi.php                # koneksi PDO PostgreSQL
└── data.sql                   # skema PostgreSQL
```

Setiap modul di `features/` nantinya dapat berisi `handler.php`, `service.php`, dan `repository.php` sesuai kebutuhan, tetap tanpa framework.
