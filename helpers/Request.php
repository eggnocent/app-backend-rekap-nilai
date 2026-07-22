<?php

declare(strict_types=1);

final class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function path(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        return is_string($path) ? rtrim($path, '/') ?: '/' : '/';
    }

    public static function json(): array
    {
        $body = file_get_contents('php://input');

        if ($body === false || $body === '') {
            return [];
        }

        $payload = json_decode($body, true);

        if (!is_array($payload)) {
            Response::error('Format JSON tidak valid.', 400);
        }

        return $payload;
    }

    public static function file(string $key): array
    {
        $file = $_FILES[$key] ?? null;
        if (!is_array($file)) {
            Response::error('File ' . $key . ' wajib diunggah.', 422);
        }

        return $file;
    }

    public static function query(string $key): ?string
    {
        $query = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY);

        if (!is_string($query)) {
            return null;
        }

        parse_str($query, $parameters);
        $value = $parameters[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/^Bearer\\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        $token = trim($matches[1]);

        return $token === '' ? null : $token;
    }

    public static function ipAddress(): ?string
    {
        $address = $_SERVER['REMOTE_ADDR'] ?? null;

        return is_string($address) && $address !== '' ? $address : null;
    }

    public static function userAgent(): ?string
    {
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        return is_string($agent) && $agent !== '' ? $agent : null;
    }
}
