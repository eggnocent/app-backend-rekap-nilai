# NilaiKu Backend

Backend ini menggunakan PHP murni dan PostgreSQL.

## Menjalankan Lokal

```bash
docker compose down -v
docker compose up --build
```

API tersedia pada `http://localhost:8000/api`.

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
