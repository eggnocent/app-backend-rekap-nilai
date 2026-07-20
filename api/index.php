<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/koneksi.php';

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'message' => 'NilaiKu API aktif.',
], JSON_UNESCAPED_UNICODE);
