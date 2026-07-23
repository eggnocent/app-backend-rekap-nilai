<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/koneksi.php';
require_once dirname(__DIR__) . '/helpers/Response.php';
require_once dirname(__DIR__) . '/helpers/Request.php';
require_once dirname(__DIR__) . '/helpers/Token.php';
require_once dirname(__DIR__) . '/helpers/ResendMailer.php';
require_once dirname(__DIR__) . '/helpers/SupabaseStorage.php';
require_once dirname(__DIR__) . '/features/activities/ActivityRepository.php';
require_once dirname(__DIR__) . '/features/activities/ActivityService.php';
require_once dirname(__DIR__) . '/features/activities/ActivityController.php';
require_once dirname(__DIR__) . '/features/auth/AuthRepository.php';
require_once dirname(__DIR__) . '/features/auth/AuthService.php';
require_once dirname(__DIR__) . '/features/auth/AuthController.php';
require_once dirname(__DIR__) . '/features/academic-terms/AcademicTermRepository.php';
require_once dirname(__DIR__) . '/features/academic-terms/AcademicTermService.php';
require_once dirname(__DIR__) . '/features/academic-terms/AcademicTermController.php';
require_once dirname(__DIR__) . '/features/academic-events/AcademicEventRepository.php';
require_once dirname(__DIR__) . '/features/academic-events/AcademicEventService.php';
require_once dirname(__DIR__) . '/features/academic-events/AcademicEventController.php';
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
require_once dirname(__DIR__) . '/features/grades/GradeRepository.php';
require_once dirname(__DIR__) . '/features/grades/GradeService.php';
require_once dirname(__DIR__) . '/features/grades/GradeController.php';
require_once dirname(__DIR__) . '/features/attendance/AttendanceRepository.php';
require_once dirname(__DIR__) . '/features/attendance/AttendanceService.php';
require_once dirname(__DIR__) . '/features/attendance/AttendanceController.php';
require_once dirname(__DIR__) . '/features/dashboard/DashboardRepository.php';
require_once dirname(__DIR__) . '/features/dashboard/DashboardService.php';
require_once dirname(__DIR__) . '/features/dashboard/DashboardController.php';
require_once dirname(__DIR__) . '/features/users/UserRepository.php';
require_once dirname(__DIR__) . '/features/users/UserService.php';
require_once dirname(__DIR__) . '/features/users/UserController.php';
require_once dirname(__DIR__) . '/middleware/AuthMiddleware.php';

header('Access-Control-Allow-Origin: ' . (getenv('CORS_ALLOWED_ORIGIN') ?: 'http://localhost:5173'));
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');

if (Request::method() === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $connection = database();
    $activityRepository = new ActivityRepository($connection);
    $activityController = new ActivityController(new ActivityService($activityRepository));
    $authRepository = new AuthRepository($connection);
    $authService = new AuthService($connection, $authRepository, $activityRepository);
    $authController = new AuthController($authService);
    $authMiddleware = new AuthMiddleware($authService);
    $academicTermController = new AcademicTermController(new AcademicTermService($connection, new AcademicTermRepository($connection), $activityRepository));
    $academicEventController = new AcademicEventController(new AcademicEventService($connection, new AcademicEventRepository($connection), $activityRepository));
    $courseController = new CourseController(new CourseService(new CourseRepository($connection), $activityRepository));
    $classService = new ClassService($connection, new ClassRepository($connection), $activityRepository);
    $classController = new ClassController($classService);
    $scheduleController = new ScheduleController($classService);
    $enrollmentController = new EnrollmentController(new EnrollmentService($connection, new EnrollmentRepository($connection), $activityRepository));
    $gradeController = new GradeController(new GradeService($connection, new GradeRepository($connection), new EnrollmentRepository($connection), $activityRepository));
    $attendanceController = new AttendanceController(new AttendanceService($connection, new AttendanceRepository($connection), new EnrollmentRepository($connection), $activityRepository));
    $dashboardController = new DashboardController(new DashboardService(new DashboardRepository($connection)));
    $userController = new UserController(new UserService($connection, new UserRepository($connection), $activityRepository));
    $method = Request::method();
    $path = Request::path();

    if ($method === 'GET' && $path === '/api') {
        Response::send([
            'message' => 'NilaiKu API aktif.',
        ]);
    }

    if ($method === 'GET' && $path === '/api/health') {
        Response::send(['status' => 'ok']);
    }

    if ($method === 'POST' && $path === '/api/auth/login') {
        $authController->login();
    }

    if ($method === 'POST' && $path === '/api/auth/forgot-password') {
        $authController->forgotPassword();
    }

    if ($method === 'POST' && $path === '/api/auth/reset-password') {
        $authController->resetPassword();
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

    if ($method === 'GET' && $path === '/api/dashboard') {
        $dashboardController->show($authMiddleware->authenticate(['admin', 'lecturer', 'student']));
    }

    if ($method === 'GET' && $path === '/api/activities') {
        $authMiddleware->authenticate(['admin']);
        $activityController->index();
    }

    if ($method === 'GET' && $path === '/api/profile') {
        $userController->profile($authMiddleware->authenticate(['student', 'lecturer']));
    }

    if ($method === 'PATCH' && $path === '/api/profile') {
        $userController->updateProfile($authMiddleware->authenticate(['student', 'lecturer']));
    }

    if ($method === 'POST' && $path === '/api/profile/avatar') {
        $userController->uploadAvatar($authMiddleware->authenticate(['student', 'lecturer']));
    }

    if ($method === 'DELETE' && $path === '/api/profile/avatar') {
        $userController->deleteAvatar($authMiddleware->authenticate(['student', 'lecturer']));
    }

    if ($method === 'GET' && $path === '/api/students') {
        $authMiddleware->authenticate(['admin']);
        $userController->students();
    }

    if ($method === 'POST' && $path === '/api/students') {
        $userController->createStudent($authMiddleware->authenticate(['admin']));
    }

    if ($method === 'PATCH' && preg_match('#^/api/students/([0-9a-fA-F-]{36})$#', $path, $matches) === 1) {
        $userController->updateStudent($matches[1], $authMiddleware->authenticate(['admin']));
    }

    if ($method === 'DELETE' && preg_match('#^/api/students/([0-9a-fA-F-]{36})$#', $path, $matches) === 1) {
        $userController->deactivateStudent($matches[1], $authMiddleware->authenticate(['admin']));
    }

    if ($method === 'GET' && $path === '/api/lecturers') {
        $authMiddleware->authenticate(['admin']);
        $userController->lecturers();
    }

    if ($method === 'POST' && $path === '/api/lecturers') {
        $userController->createLecturer($authMiddleware->authenticate(['admin']));
    }

    if ($method === 'PATCH' && preg_match('#^/api/lecturers/([0-9a-fA-F-]{36})$#', $path, $matches) === 1) {
        $userController->updateLecturer($matches[1], $authMiddleware->authenticate(['admin']));
    }

    if ($method === 'GET' && $path === '/api/academic-events') {
        $academicEventController->index($authMiddleware->authenticate(['admin', 'lecturer', 'student']));
    }

    if ($method === 'POST' && $path === '/api/academic-events') {
        $academicEventController->create($authMiddleware->authenticate(['admin']));
    }

    if ($method === 'PATCH' && preg_match('#^/api/academic-events/([0-9a-fA-F-]{36})$#', $path, $matches) === 1) {
        $academicEventController->update($matches[1], $authMiddleware->authenticate(['admin']));
    }

    if ($method === 'DELETE' && preg_match('#^/api/academic-events/([0-9a-fA-F-]{36})$#', $path, $matches) === 1) {
        $academicEventController->delete($matches[1], $authMiddleware->authenticate(['admin']));
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

    if ($method === 'DELETE' && preg_match('#^/api/courses/([0-9a-fA-F-]{36})$#', $path, $matches) === 1) {
        $courseController->archive($matches[1], $authMiddleware->authenticate(['admin']));
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
        $classController->show($matches[1], $authMiddleware->authenticate(['admin', 'lecturer', 'student']));
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

    if ($method === 'GET' && preg_match('#^/api/classes/([0-9a-fA-F-]{36})/grades$#', $path, $matches) === 1) {
        $gradeController->roster($matches[1], $authMiddleware->authenticate(['lecturer']));
    }

    if ($method === 'PUT' && preg_match('#^/api/grades/enrollments/([0-9a-fA-F-]{36})$#', $path, $matches) === 1) {
        $gradeController->saveDraft($matches[1], $authMiddleware->authenticate(['lecturer']));
    }

    if ($method === 'GET' && $path === '/api/grades') {
        $authMiddleware->authenticate(['admin']);
        $gradeController->index();
    }

    if ($method === 'GET' && $path === '/api/grades/me') {
        $gradeController->mine($authMiddleware->authenticate(['student']));
    }

    if ($method === 'GET' && $path === '/api/grades/transcript') {
        $gradeController->transcript($authMiddleware->authenticate(['student']));
    }

    if ($method === 'POST' && preg_match('#^/api/grades/([0-9a-fA-F-]{36})/submit$#', $path, $matches) === 1) {
        $gradeController->submit($matches[1], $authMiddleware->authenticate(['lecturer']));
    }

    if ($method === 'POST' && preg_match('#^/api/grades/([0-9a-fA-F-]{36})/verify$#', $path, $matches) === 1) {
        $gradeController->verify($matches[1], $authMiddleware->authenticate(['admin']));
    }

    if ($method === 'POST' && preg_match('#^/api/grades/([0-9a-fA-F-]{36})/return$#', $path, $matches) === 1) {
        $gradeController->returnGrade($matches[1], $authMiddleware->authenticate(['admin']));
    }

    if ($method === 'POST' && preg_match('#^/api/grades/([0-9a-fA-F-]{36})/publish$#', $path, $matches) === 1) {
        $gradeController->publish($matches[1], $authMiddleware->authenticate(['admin']));
    }

    if ($method === 'GET' && $path === '/api/attendance') {
        $authMiddleware->authenticate(['admin']);
        $attendanceController->index();
    }

    if ($method === 'GET' && $path === '/api/attendance/me') {
        $attendanceController->mine($authMiddleware->authenticate(['student']));
    }

    if ($method === 'GET' && preg_match('#^/api/classes/([0-9a-fA-F-]{36})/attendance$#', $path, $matches) === 1) {
        $attendanceController->classAttendance($matches[1], $authMiddleware->authenticate(['admin', 'lecturer']));
    }

    if ($method === 'GET' && preg_match('#^/api/attendance-meetings/([0-9a-fA-F-]{36})$#', $path, $matches) === 1) {
        $attendanceController->meeting($matches[1], $authMiddleware->authenticate(['admin', 'lecturer']));
    }

    if ($method === 'POST' && preg_match('#^/api/classes/([0-9a-fA-F-]{36})/attendance-meetings$#', $path, $matches) === 1) {
        $attendanceController->createMeeting($matches[1], $authMiddleware->authenticate(['lecturer']));
    }

    if ($method === 'PUT' && preg_match('#^/api/attendance-meetings/([0-9a-fA-F-]{36})/records/([0-9a-fA-F-]{36})$#', $path, $matches) === 1) {
        $attendanceController->setRecord($matches[1], $matches[2], $authMiddleware->authenticate(['lecturer']));
    }

    Response::error('Endpoint tidak ditemukan.', 404);
} catch (Throwable) {
    Response::error('Terjadi kesalahan pada server.', 500);
}
