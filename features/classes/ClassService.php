<?php

declare(strict_types=1);

final class ClassService
{
    public function __construct(
        private PDO $connection,
        private ClassRepository $repository,
        private ActivityRepository $activityRepository
    ) {
    }

    public function all(array $user): array
    {
        $termId = Request::query('term_id');
        $courseId = Request::query('course_id');
        $status = Request::query('status');

        if ($status !== null && !in_array($status, ['Aktif', 'Ditutup'], true)) {
            Response::error('Filter status tidak valid.', 422);
        }

        return $this->repository->all($termId, $courseId, $status, $user['role'] === 'lecturer' ? $user['lecturer_profile_id'] : null);
    }

    public function find(string $id, array $user): array
    {
        $class = $this->repository->find($id);

        if ($class === null) {
            Response::error('Kelas tidak ditemukan.', 404);
        }

        $this->authorizeAccess($class, $user);

        return $class;
    }

    public function create(array $payload, array $user): array
    {
        $class = $this->validatedClass($payload, null);
        $this->assertClassAvailable($class, null);
        $this->connection->beginTransaction();

        try {
            $classId = $this->repository->create($class, $user['id']);
            $this->repository->replaceSchedules($classId, $class['schedules'], $user['id']);
            $created = $this->repository->find($classId);
            $this->activityRepository->create($user['id'], 30, 'create_class', 'Created class ' . $created['code'] . '.', $user['role'], $user['email']);
            $this->connection->commit();

            return $created;
        } catch (Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    public function update(string $id, array $payload, array $user): array
    {
        $existing = $this->repository->find($id);

        if ($existing === null) {
            Response::error('Kelas tidak ditemukan.', 404);
        }

        if ($existing['status'] !== 'Aktif') {
            Response::error('Kelas yang sudah ditutup tidak dapat diubah.', 422);
        }

        $class = $this->validatedClass(array_merge($existing, $payload), $existing['schedules']);
        $this->assertClassAvailable($class, $id);
        $this->connection->beginTransaction();

        try {
            $this->repository->update($id, $class, $user['id']);

            if (array_key_exists('schedules', $payload)) {
                $this->repository->replaceSchedules($id, $class['schedules'], $user['id']);
            }

            $updated = $this->repository->find($id);
            $this->activityRepository->create($user['id'], 31, 'update_class', 'Updated class ' . $updated['code'] . '.', $user['role'], $user['email']);
            $this->connection->commit();

            return $updated;
        } catch (Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    public function close(string $id, array $user): array
    {
        $class = $this->repository->find($id);

        if ($class === null) {
            Response::error('Kelas tidak ditemukan.', 404);
        }

        if ($class['status'] === 'Ditutup') {
            Response::error('Kelas sudah ditutup.', 422);
        }

        $this->repository->close($id, $user['id']);
        $closed = $this->repository->find($id);
        $this->activityRepository->create($user['id'], 32, 'close_class', 'Closed class ' . $closed['code'] . '.', $user['role'], $user['email']);

        return $closed;
    }

    public function schedules(array $user): array
    {
        return $this->repository->all(Request::query('term_id'), Request::query('class_id'), null, $user['role'] === 'lecturer' ? $user['lecturer_profile_id'] : null);
    }

    public function replaceSchedules(string $id, array $payload, array $user): array
    {
        $class = $this->repository->find($id);

        if ($class === null) {
            Response::error('Kelas tidak ditemukan.', 404);
        }

        if ($class['status'] !== 'Aktif') {
            Response::error('Jadwal kelas yang ditutup tidak dapat diubah.', 422);
        }

        $schedules = $this->validatedSchedules($payload['schedules'] ?? null);

        if ($this->repository->scheduleConflicts($class['term_id'], $class['lecturer_id'], $schedules, $id)) {
            Response::error('Jadwal dosen atau ruang bertabrakan dengan kelas aktif lain.', 422);
        }

        $this->connection->beginTransaction();

        try {
            $this->repository->replaceSchedules($id, $schedules, $user['id']);
            $updated = $this->repository->find($id);
            $this->activityRepository->create($user['id'], 33, 'replace_class_schedules', 'Updated schedules for class ' . $updated['code'] . '.', $user['role'], $user['email']);
            $this->connection->commit();

            return $updated;
        } catch (Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    private function validatedClass(array $payload, ?array $fallbackSchedules): array
    {
        $termId = $this->string($payload, 'term_id');
        $courseId = $this->string($payload, 'course_id');
        $lecturerId = $this->string($payload, 'lecturer_id');
        $code = strtoupper($this->string($payload, 'code'));
        $capacity = filter_var($payload['capacity'] ?? null, FILTER_VALIDATE_INT);
        $schedules = array_key_exists('schedules', $payload) ? $this->validatedSchedules($payload['schedules']) : $fallbackSchedules;

        if ($capacity === false || $capacity < 1 || !is_array($schedules) || $schedules === []) {
            Response::error('Kapasitas dan jadwal kelas tidak valid.', 422);
        }

        return [
            'term_id' => $termId,
            'course_id' => $courseId,
            'lecturer_id' => $lecturerId,
            'code' => $code,
            'capacity' => $capacity,
            'status' => 'Aktif',
            'schedules' => $schedules,
        ];
    }

    private function validatedSchedules(mixed $schedules): array
    {
        if (!is_array($schedules) || $schedules === []) {
            Response::error('Minimal satu jadwal kelas wajib diisi.', 422);
        }

        $validated = [];

        foreach ($schedules as $schedule) {
            if (!is_array($schedule)) {
                Response::error('Format jadwal tidak valid.', 422);
            }

            $day = filter_var($schedule['day_of_week'] ?? null, FILTER_VALIDATE_INT);
            $start = $schedule['start_time'] ?? null;
            $end = $schedule['end_time'] ?? null;
            $room = $schedule['room'] ?? null;

            if ($day === false || $day < 1 || $day > 6 || !is_string($start) || !preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', $start) || !is_string($end) || !preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', $end) || $start >= $end || !is_string($room) || trim($room) === '') {
                Response::error('Data jadwal tidak valid.', 422);
            }

            $validated[] = [
                'day_of_week' => $day,
                'start_time' => $start,
                'end_time' => $end,
                'room' => trim($room),
            ];
        }

        return $validated;
    }

    private function assertClassAvailable(array $class, ?string $exceptId): void
    {
        if (!$this->repository->termExists($class['term_id'])) {
            Response::error('Semester tidak ditemukan.', 422);
        }

        if (!$this->repository->activeCourseExists($class['course_id'])) {
            Response::error('Mata kuliah aktif tidak ditemukan.', 422);
        }

        if (!$this->repository->lecturerExists($class['lecturer_id'])) {
            Response::error('Dosen pengampu tidak ditemukan.', 422);
        }

        if ($this->repository->codeExists($class['term_id'], $class['code'], $exceptId)) {
            Response::error('Kode kelas sudah dipakai pada semester ini.', 422);
        }

        if ($this->repository->scheduleConflicts($class['term_id'], $class['lecturer_id'], $class['schedules'], $exceptId)) {
            Response::error('Jadwal dosen atau ruang bertabrakan dengan kelas aktif lain.', 422);
        }
    }

    private function authorizeLecturer(array $class, array $user): void
    {
        if ($user['role'] === 'lecturer' && $class['lecturer_id'] !== $user['lecturer_profile_id']) {
            Response::error('Anda tidak memiliki akses untuk kelas ini.', 403);
        }
    }

    private function authorizeAccess(array $class, array $user): void
    {
        if ($user['role'] === 'student') {
            $studentId = $user['student_profile_id'] ?? null;

            if (!is_string($studentId) || $studentId === '' || !$this->repository->studentHasActiveEnrollment($studentId, $class['id'])) {
                Response::error('Anda tidak memiliki akses untuk kelas ini.', 403);
            }

            return;
        }

        $this->authorizeLecturer($class, $user);
    }

    private function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (!is_string($value) || trim($value) === '') {
            Response::error('Field ' . $key . ' wajib diisi.', 422);
        }

        return trim($value);
    }
}
