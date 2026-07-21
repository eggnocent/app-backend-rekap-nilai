<?php

declare(strict_types=1);

final class Response
{
    public static function send(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, int $status): never
    {
        self::send([
            'message' => $message,
        ], $status);
    }
}
