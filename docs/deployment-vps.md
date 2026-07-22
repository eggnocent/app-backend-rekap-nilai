# Deployment VPS

Dokumen ini menjalankan backend NilaiKu pada satu VPS Ubuntu LTS. Stack produksi berisi Nginx, PHP-FPM, dan PostgreSQL melalui Docker Compose. Supabase Storage serta Resend tetap berjalan sebagai layanan eksternal.

## Prasyarat

- Domain API sudah mengarah ke alamat IPv4 VPS, misalnya `api.example.com`.
- Frontend produksi sudah tersedia pada domain HTTPS.
- VPS memiliki Docker Engine, Docker Compose plugin, Git, dan akses SSH berbasis key.
- Firewall hanya mengizinkan port `22`, `80`, dan `443`.

```bash
sudo apt update
sudo apt install -y ca-certificates curl git ufw
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo \"$VERSION_CODENAME\") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo usermod -aG docker "$USER"
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

Masuk ulang ke sesi SSH setelah menambahkan user ke grup Docker.

## Instalasi awal

```bash
sudo mkdir -p /opt/nilaiku
sudo chown "$USER":"$USER" /opt/nilaiku
git clone <repository-backend> /opt/nilaiku/app-backend
cp /opt/nilaiku/app-backend/.env.production.example /opt/nilaiku/.env
chmod 600 /opt/nilaiku/.env
```

Isi `/opt/nilaiku/.env` dengan domain final, URL frontend HTTPS, password PostgreSQL acak, kredensial Resend, dan kredensial Supabase. Jangan menyimpan file ini di repository.

Buat bucket Supabase `avatars` sebagai public bucket. Batasi MIME ke `image/jpeg`, `image/png`, dan `image/webp`, dengan ukuran maksimum 1 MB.

## Sertifikat HTTPS dan stack

Jalankan perintah dari direktori `/opt/nilaiku/app-backend`. Tahap bootstrap membuat Nginx HTTP agar challenge Let’s Encrypt dapat diakses. Pastikan DNS domain sudah selesai propagasi sebelum menjalankan Certbot.

```bash
cd /opt/nilaiku/app-backend
NGINX_TEMPLATE_DIR=./nginx/templates/bootstrap docker compose --env-file /opt/nilaiku/.env -f docker-compose.production.yml up -d app database nginx
docker compose --env-file /opt/nilaiku/.env -f docker-compose.production.yml --profile certbot run --rm certbot certonly --webroot -w /var/www/certbot -d "$(grep '^DEPLOYMENT_DOMAIN=' /opt/nilaiku/.env | cut -d= -f2-)" --email "$(grep '^CERTBOT_EMAIL=' /opt/nilaiku/.env | cut -d= -f2-)" --agree-tos --no-eff-email
docker compose --env-file /opt/nilaiku/.env -f docker-compose.production.yml up -d --force-recreate nginx
./scripts/migrate.sh /opt/nilaiku/.env
curl --fail https://"$(grep '^DEPLOYMENT_DOMAIN=' /opt/nilaiku/.env | cut -d= -f2-)"/api/health
```

`GET /api/health` harus menghasilkan respons JSON dengan `status` bernilai `ok`.

## Operasional

Gunakan deploy script untuk pembaruan rutin. Skrip mengambil perubahan fast-forward, membuat backup sebelum perubahan, membangun image, menjalankan stack, lalu menerapkan migrasi baru.

```bash
cd /opt/nilaiku/app-backend
./scripts/deploy-production.sh /opt/nilaiku/.env
```

Jadwalkan backup dan renewal sertifikat dengan cron root.

```cron
0 2 * * * cd /opt/nilaiku/app-backend && ./scripts/backup-postgres.sh /opt/nilaiku/.env
15 3 * * * cd /opt/nilaiku/app-backend && docker compose --env-file /opt/nilaiku/.env -f docker-compose.production.yml --profile certbot run --rm certbot renew --webroot -w /var/www/certbot && docker compose --env-file /opt/nilaiku/.env -f docker-compose.production.yml exec -T nginx nginx -s reload
```

Backup PostgreSQL disimpan di `/var/backups/nilaiku` dan dihapus setelah tujuh hari. Uji pemulihan secara berkala ke database kosong. Sebelum aplikasi digunakan untuk data penting, salin backup tersebut ke penyimpanan eksternal.

## Pemeriksaan pascadeploy

- HTTPS aktif dan sertifikat valid untuk domain API.
- `https://<domain-api>/api/health` merespons sukses.
- Login frontend, reset password Resend, dan upload avatar Supabase berjalan.
- Port PostgreSQL dan PHP-FPM tidak terbuka dari internet.
- Data tetap tersedia setelah `docker compose restart`.
