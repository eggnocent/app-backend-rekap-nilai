<?php

declare(strict_types=1);

/**
 * Membuat koneksi PostgreSQL saat pertama kali diperlukan.
 * Konfigurasi dibaca dari environment agar kredensial tidak disimpan di Git.
 */
function database(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '5432';
    $name = getenv('DB_NAME') ?: 'nilaiku';
    $user = getenv('DB_USER') ?: 'postgres';
    $password = getenv('DB_PASSWORD') ?: '';

    $dsn = "pgsql:host={$host};port={$port};dbname={$name}";
    $connection = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}
