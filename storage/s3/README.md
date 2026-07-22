# Supabase Storage

Avatar disimpan pada bucket publik `avatars` melalui REST API Supabase Storage dari backend PHP.

Isi `.env` dengan `SUPABASE_URL`, `SUPABASE_SERVICE_ROLE_KEY`, dan `SUPABASE_STORAGE_BUCKET`. Service role key hanya digunakan backend.

Di Dashboard Supabase, buat bucket `avatars` sebagai public bucket dan atur batas ukuran file 1 MB dengan tipe MIME `image/jpeg`, `image/png`, dan `image/webp`.
