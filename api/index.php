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
require_once dirname(__DIR__) . '/features/academic-terms/AcademicTermRepository.php';
require_once dirname(__DIR__) . '/features/academic-terms/AcademicTermService.php';
require_once dirname(__DIR__) . '/features/academic-terms/AcademicTermController.php';
require_once dirname(__DIR__) . '/features/courses/CourseRepository.php';
require_once dirname(__DIR__) . '/features/courses/CourseService.php';
require_once dirname(__DIR__) . '/features/courses/CourseController.php';
require_once dirname(__DIR__) . '/features/classes/ClassRepository.php';
require_once dirname(__DIR__) . '/features/classes/ClassService.php';
require_once dirname(__DIR__) . '/features/classes/ClassController.php';
require_once dirname(__DIR__) . '/features/classes/ScheduleController.php';
require_once dirname(__DIR__) . '/features/enrollments/EnrollmentRepository.php';
require_once dirname(__DIR__) . '/features/enrollments/EnrollmentService.php';
require_once dirname(__DIR__) . '/features/enrollments/EnrollmentController.php';
require_once dirname(__DIR__) . '/middleware/AuthMiddleware.php';

try {
    $connection = database();
    $activityRepository = new ActivityRepository($connection);
    $authRepository = new AuthRepository($connection);
    $authService = new AuthService($authRepository, $activityRepository);
    $authController = new AuthController($authService);
    $authMiddleware = new AuthMiddleware($authService);
    $academicTermController = new AcademicTermController(new AcademicTermService($connection, new AcademicTermRepository($connection), $activityRepository));
    $courseController = new CourseController(new CourseService(new CourseRepository($connection), $activityRepository));
    $classService = new ClassService($connection, new ClassRepository($connection), $activityRepository);
    $classController = new ClassController($classService);
    $scheduleController = new ScheduleController($classService);
    $enrollmentController = new EnrollmentController(new EnrollmentService($connection, new EnrollmentRepository($connection), $activityRepository));
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

    if ($method === 'GET' && $path === '/api/academic-terms/active') {
        $authMiddleware->authenticate();
        $academicTermController->active();
    }

    if ($method === 'GET' && $path === '/api/academic-terms') {
        $authMiddleware->authenticate(['admin']);
        $academicTermController->index();
    }

    if ($method === 'POST' && $path === '/api/academic-terms') {
        $academicTermController->create($authMiddleware->authenticate(['admin']));
    }

    if ($method === 'PATCH' && preg_match('#^/api/academic-terms/([0-9a-fA-F-]{36})$#', $path, $matches) === 1) {
        $academicTermController->update($matches[1], $authMiddleware->authenticate(['admin']));
    }

    if ($method === 'GET' && $path === '/api/courses') {
        $courseController->index($authMiddleware->authenticate());
    }

    if ($method === 'POST' && $path === '/api/courses') {
        $courseController->create($authMiddleware->authenticate(['admin']));
    }

    if ($method === 'PATCH' && preg_match('#^/api/courses/([0-9a-fA-F-]{36})$#', $path, $matches) === 1) {
        $courseController->update($matches[1], $authMiddleware->authenticate(['admin']));
    }

    if ($method === 'GET' && $path === '/api/schedules') {
        $scheduleController->index($authMiddleware->authenticate(['admin', 'lecturer']));
    }

    if ($method === 'GET' && $path === '/api/schedules/me') {
        $enrollmentController->mySchedule($authMiddleware->authenticate(['student']));
    }

    if ($method === 'PUT' && preg_match('#^/api/classes/([0-9a-fA-F-]{36})/schedules$#', $path, $matches) === 1) {
        $scheduleController->replace($matches[1], $authMiddleware->authenticate(['admin']));
    }

    if ($method === 'GET' && $path === '/api/classes') {
        $classController->index($authMiddleware->authenticate(['admin', 'lecturer']));
    }

    if ($method === 'POST' && $path === '/api/classes') {
        $classController->create($authMiddleware->authenticate(['admin']));
    }

    if ($method === 'POST' && preg_match('#^/api/classes/([0-9a-fA-F-]{36})/close$#', $path, $matches) === 1) {
        $classController->close($matches[1], $authMiddleware->authenticate(['admin']));
    }

    if ($method === 'GET' && preg_match('#^/api/classes/([0-9a-fA-F-]{36})$#', $path, $matches) === 1) {
        $classController->show($matches[1], $authMiddleware->authenticate(['admin', 'lecturer']));
    }

    if ($method === 'PATCH' && preg_match('#^/api/classes/([0-9a-fA-F-]{36})$#', $path, $matches) === 1) {
        $classController->update($matches[1], $authMiddleware->authenticate(['admin']));
    }

    if ($method === 'GET' && $path === '/api/enrollments') {
        $authMiddleware->authenticate(['admin']);
        $enrollmentController->index();
    }

    if ($method === 'POST' && $path === '/api/enrollments') {
        $enrollmentController->create($authMiddleware->authenticate(['admin']));
    }

    if ($method === 'GET' && $path === '/api/enrollments/me') {
        $enrollmentController->mine($authMiddleware->authenticate(['student']));
    }

    if ($method === 'POST' && preg_match('#^/api/enrollments/([0-9a-fA-F-]{36})/cancel$#', $path, $matches) === 1) {
        $enrollmentController->cancel($matches[1], $authMiddleware->authenticate(['admin']));
    }

    Response::error('Endpoint tidak ditemukan.', 404);
} catch (Throwable) {
    Response::error('Terjadi kesalahan pada server.', 500);
}
