<?php

declare(strict_types=1);

final class EnrollmentService
{
    public function __construct(
        private PDO $connection,
        private EnrollmentRepository $repository,
        private ActivityRepository $activityRepository
    ) {
    }

    public function all(): array
    {
        $status = Request::query('status');

        if ($status !== null && !in_array($status, ['Terdaftar', 'Dibatalkan'], true)) {
            Response::error('Filter status tidak valid.', 422);
        }

        return $this->repository->all(Request::query('student_id'), Request::query('class_id'), $status, Pagination::fromRequest());
    }

    public function create(array $payload, array $user): array
    {
        $studentId = $this->requiredUuid($payload, 'student_id');
        $classId = $this->requiredUuid($payload, 'class_id');
        $this->connection->beginTransaction();

        try {
            $term = $this->repository->activeTerm();

            if ($term === null) {
                Response::error('Semester aktif tidak ditemukan.', 422);
            }

            $student = $this->repository->findStudent($studentId);

            if ($student === null || strtolower((string) $student['status']) !== 'active') {
                Response::error('Mahasiswa aktif tidak ditemukan.', 422);
            }

            $class = $this->repository->lockClass($classId);

            if ($class === null || $class['status'] !== 'Aktif' || $class['term_id'] !== $term['id']) {
                Response::error('Kelas aktif pada semester aktif tidak ditemukan.', 422);
            }

            if ($this->repository->enrolledCount($classId) >= (int) $class['capacity']) {
                Response::error('Kapasitas kelas sudah penuh.', 422);
            }

            if ($this->repository->activeEnrollmentExists($studentId, $classId)) {
                Response::error('Mahasiswa sudah terdaftar di kelas ini.', 422);
            }

            if ($this->repository->activeCourseEnrollmentExists($studentId, $class['term_id'], $class['course_id'])) {
                Response::error('Mahasiswa sudah mengambil mata kuliah ini pada kelas paralel.', 422);
            }

            if ($this->repository->hasScheduleConflict($studentId, $class['term_id'], $classId)) {
                Response::error('Jadwal kelas bertabrakan dengan KRS mahasiswa.', 422);
            }

            $enrollmentId = $this->repository->create($studentId, $classId, $user['id']);
            $enrollment = $this->repository->find($enrollmentId);
            $this->activityRepository->create($user['id'], 40, 'create_enrollment', 'Enrolled ' . $student['name'] . ' in class ' . $class['course_code'] . '.', $user['role'], $user['email']);
            $this->connection->commit();

            return $enrollment;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    public function cancel(string $id, array $user): array
    {
        $enrollment = $this->repository->find($id);

        if ($enrollment === null) {
            Response::error('Enrollment tidak ditemukan.', 404);
        }

        if ($enrollment['status'] !== 'Terdaftar') {
            Response::error('Enrollment sudah dibatalkan.', 422);
        }

        $this->connection->beginTransaction();

        try {
            $this->repository->cancel($id, $user['id']);
            $canceled = $this->repository->find($id);
            $this->activityRepository->create($user['id'], 41, 'cancel_enrollment', 'Canceled enrollment for ' . $enrollment['student_name'] . ' in class ' . $enrollment['class_code'] . '.', $user['role'], $user['email']);
            $this->connection->commit();

            return $canceled;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    public function mine(array $user): array
    {
        $term = $this->repository->activeTerm();

        if ($term === null) {
            Response::error('Semester aktif tidak ditemukan.', 404);
        }

        $studentId = $user['student_profile_id'] ?? null;

        if (!is_string($studentId) || $studentId === '') {
            Response::error('Profil mahasiswa tidak ditemukan.', 403);
        }

        return $this->repository->allForStudent($studentId, $term['id']);
    }

    public function mySchedule(array $user): array
    {
        $enrollments = $this->mine($user);
        $schedules = [];

        foreach ($enrollments as $enrollment) {
            foreach ($enrollment['schedules'] as $schedule) {
                $schedules[] = [
                    ...$schedule,
                    'enrollment_id' => $enrollment['id'],
                    'class_id' => $enrollment['class_id'],
                    'class_code' => $enrollment['class_code'],
                    'course_id' => $enrollment['course_id'],
                    'course_code' => $enrollment['course_code'],
                    'course_name' => $enrollment['course_name'],
                    'lecturer_id' => $enrollment['lecturer_id'],
                    'lecturer_name' => $enrollment['lecturer_name'],
                ];
            }
        }

        usort($schedules, fn (array $left, array $right): int => [$left['day_of_week'], $left['start_time']] <=> [$right['day_of_week'], $right['start_time']]);

        return $schedules;
    }

    private function requiredUuid(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (!is_string($value) || !preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value)) {
            Response::error('Field ' . $key . ' harus berupa UUID yang valid.', 422);
        }

        return strtolower($value);
    }
}
