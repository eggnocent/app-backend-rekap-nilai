<?php

declare(strict_types=1);

final class ResendMailer
{
    public function sendPasswordReset(string $email, string $name, string $token): void
    {
        $apiKey = getenv('RESEND_API_KEY') ?: '';
        $from = getenv('RESEND_FROM') ?: '';
        $frontendUrl = rtrim(getenv('APP_FRONTEND_URL') ?: '', '/');

        if ($apiKey === '' || $from === '' || $frontendUrl === '') {
            throw new RuntimeException('Konfigurasi Resend belum lengkap.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('Ekstensi cURL tidak tersedia.');
        }

        $resetUrl = $frontendUrl . '/reset-password?token=' . rawurlencode($token);
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $payload = json_encode([
            'from' => $from,
            'to' => [$email],
            'subject' => 'Reset password NilaiKu',
            'html' => '<p>Halo ' . $safeName . ',</p><p>Klik tautan berikut untuk mengatur ulang password Anda:</p><p><a href="' . $safeUrl . '">Reset password</a></p><p>Tautan ini berlaku selama 30 menit.</p>',
            'text' => 'Halo ' . $name . ",\n\nAtur ulang password Anda melalui tautan berikut:\n" . $resetUrl . "\n\nTautan ini berlaku selama 30 menit.",
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            throw new RuntimeException('Payload email tidak dapat dibuat.');
        }

        $request = curl_init('https://api.resend.com/emails');
        if ($request === false) {
            throw new RuntimeException('Koneksi Resend tidak dapat dibuat.');
        }

        curl_setopt_array($request, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'User-Agent: NilaiKu-Backend/1.0',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($request);
        $status = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
        $error = curl_error($request);
        curl_close($request);

        if ($response === false || $status < 200 || $status >= 300) {
            throw new RuntimeException($error === '' ? 'Resend menolak pengiriman email.' : $error);
        }
    }
}
