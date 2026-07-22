<?php

declare(strict_types=1);

final class SupabaseStorage
{
    private const MAX_AVATAR_SIZE = 1048576;

    private array $avatarTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function uploadAvatar(array $file, string $userId): string
    {
        $this->ensureConfigured();
        $this->validateUpload($file);
        $mimeType = $this->mimeType($file['tmp_name']);
        $extension = $this->avatarTypes[$mimeType] ?? null;
        if ($extension === null) {
            Response::error('Foto harus berformat JPEG, PNG, atau WebP.', 422);
        }

        $path = $userId . '/' . $this->uuidV4() . '.' . $extension;
        $contents = file_get_contents($file['tmp_name']);
        if ($contents === false) {
            throw new RuntimeException('File foto tidak dapat dibaca.');
        }

        $this->request('POST', $this->objectUrl($path), $contents, [
            'Content-Type: ' . $mimeType,
            'x-upsert: false',
        ]);

        return $path;
    }

    public function delete(string $path): void
    {
        if (!$this->isAvatarPath($path)) {
            return;
        }

        $this->ensureConfigured();
        $this->request('DELETE', $this->objectUrl($path));
    }

    public function publicUrl(?string $path): ?string
    {
        if ($path === null || !$this->isAvatarPath($path)) {
            return null;
        }

        $url = rtrim(getenv('SUPABASE_URL') ?: '', '/');
        $bucket = getenv('SUPABASE_STORAGE_BUCKET') ?: 'avatars';
        if ($url === '' || $bucket === '') {
            return null;
        }

        return $url . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . $this->encodePath($path);
    }

    private function validateUpload(array $file): void
    {
        $error = $file['error'] ?? null;
        if (!is_int($error) || $error !== UPLOAD_ERR_OK) {
            Response::error('Foto gagal diunggah.', 422);
        }

        $tmpName = $file['tmp_name'] ?? null;
        $size = $file['size'] ?? null;
        if (!is_string($tmpName) || !is_uploaded_file($tmpName) || !is_int($size) || $size < 1 || $size > self::MAX_AVATAR_SIZE) {
            Response::error('Ukuran foto harus antara 1 byte hingga 1 MB.', 422);
        }
    }

    private function mimeType(string $file): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new RuntimeException('Pemeriksaan file tidak tersedia.');
        }

        $mimeType = finfo_file($finfo, $file);
        finfo_close($finfo);

        if (!is_string($mimeType)) {
            throw new RuntimeException('Jenis file tidak dapat diperiksa.');
        }

        return $mimeType;
    }

    private function objectUrl(string $path): string
    {
        return rtrim(getenv('SUPABASE_URL') ?: '', '/') . '/storage/v1/object/' . rawurlencode($this->bucket()) . '/' . $this->encodePath($path);
    }

    private function request(string $method, string $url, ?string $body = null, array $headers = []): void
    {
        $request = curl_init($url);
        if ($request === false) {
            throw new RuntimeException('Koneksi Supabase Storage tidak dapat dibuat.');
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array_merge([
                'Authorization: Bearer ' . $this->serviceRoleKey(),
                'apikey: ' . $this->serviceRoleKey(),
                'User-Agent: NilaiKu-Backend/1.0',
            ], $headers),
            CURLOPT_TIMEOUT => 30,
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($request, $options);
        $response = curl_exec($request);
        $status = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
        $error = curl_error($request);
        curl_close($request);

        if ($response === false || $status < 200 || $status >= 300) {
            throw new RuntimeException($error === '' ? 'Supabase Storage menolak permintaan.' : $error);
        }
    }

    private function ensureConfigured(): void
    {
        if (rtrim(getenv('SUPABASE_URL') ?: '', '/') === '' || $this->serviceRoleKey() === '' || $this->bucket() === '') {
            Response::error('Konfigurasi Supabase Storage belum lengkap.', 503);
        }
    }

    private function serviceRoleKey(): string
    {
        return getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '';
    }

    private function bucket(): string
    {
        return getenv('SUPABASE_STORAGE_BUCKET') ?: 'avatars';
    }

    private function isAvatarPath(string $path): bool
    {
        return preg_match('#^[0-9a-fA-F-]{36}/[0-9a-fA-F-]{36}\.(jpg|png|webp)$#', $path) === 1;
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
