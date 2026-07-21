<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/koneksi.php';
require_once dirname(__DIR__) . '/helpers/Response.php';
require_once dirname(__DIR__) . '/helpers/Request.php';
require_once dirname(__DIR__) . '/helpers/Token.php';
require_once dirname(__DIR__) . '/features/activities/ActivityRepository.php';
require_once dirname(__DIR__) . '/features/auth/AuthRepository.php';
require_once dirname(__DIR__) . '/features/auth/AuthService.php';
require_once dirname(__DIR__) . '/features/auth/AuthController.php';
require_once dirname(__DIR__) . '/middleware/AuthMiddleware.php';

try {
    $connection = database();
    $activityRepository = new ActivityRepository($connection);
    $authRepository = new AuthRepository($connection);
    $authService = new AuthService($authRepository, $activityRepository);
    $authController = new AuthController($authService);
    $authMiddleware = new AuthMiddleware($authService);
    $method = Request::method();
    $path = Request::path();

    if ($method === 'GET' && $path === '/api') {
        Response::send([
            'message' => 'NilaiKu API aktif.',
        ]);
    }

    if ($method === 'POST' && $path === '/api/auth/login') {
        $authController->login();
    }

    if ($method === 'GET' && $path === '/api/auth/me') {
        $authController->me($authMiddleware->authenticate());
    }

    if ($method === 'POST' && $path === '/api/auth/logout') {
        $authController->logout($authMiddleware->authenticate());
    }

    Response::error('Endpoint tidak ditemukan.', 404);
} catch (Throwable) {
    Response::error('Terjadi kesalahan pada server.', 500);
}
