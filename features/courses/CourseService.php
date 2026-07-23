<?php

declare(strict_types=1);

final class CourseService
{
    public function __construct(
        private CourseRepository $repository,
        private ActivityRepository $activityRepository
    ) {
    }

    public function all(array $user): array
    {
        $status = Request::query('status');
        $semester = Request::query('semester');
        $search = Request::query('search');
        $includeArchived = $user['role'] === 'admin';

        if ($status !== null && !in_array($status, ['Aktif', 'Arsip'], true)) {
            Response::error('Filter status tidak valid.', 422);
        }

        if ($semester !== null && (!ctype_digit($semester) || (int) $semester < 1)) {
            Response::error('Filter semester tidak valid.', 422);
        }

        return $this->repository->all($status, $semester, $search, $includeArchived);
    }

    public function create(array $payload, array $user): array
    {
        $course = $this->validatedCourse($payload);
        $created = $this->repository->create($course, $user['id']);
        $this->activityRepository->create($user['id'], 20, 'create_course', 'Created course ' . $created['code'] . '.', $user['role'], $user['email']);

        return $created;
    }

    public function update(string $id, array $payload, array $user): array
    {
        $existing = $this->repository->find($id);

        if ($existing === null) {
            Response::error('Mata kuliah tidak ditemukan.', 404);
        }

        $course = $this->validatedCourse(array_merge($existing, $payload));
        $updated = $this->repository->update($id, $course, $user['id']);
        $activity = $updated['status'] === 'Arsip' ? 'archive_course' : 'update_course';
        $this->activityRepository->create($user['id'], 21, $activity, 'Updated course ' . $updated['code'] . '.', $user['role'], $user['email']);

        return $updated;
    }

    public function archive(string $id, array $user): array
    {
        $course = $this->repository->find($id);
        if ($course === null) {
            Response::error('Mata kuliah tidak ditemukan.', 404);
        }
        if ($course['status'] === 'Arsip') {
            Response::error('Mata kuliah sudah diarsipkan.', 422);
        }

        $archived = $this->repository->archive($id, $user['id']);
        $this->activityRepository->create($user['id'], 22, 'archive_course', 'Archived course ' . $archived['code'] . '.', $user['role'], $user['email']);
        return $archived;
    }

    private function validatedCourse(array $payload): array
    {
        $code = strtoupper($this->string($payload, 'code'));
        $name = $this->string($payload, 'name');
        $lecturerId = $this->string($payload, 'lecturer_id');
        $credits = filter_var($payload['credits'] ?? null, FILTER_VALIDATE_INT);
        $recommendedSemester = filter_var($payload['recommended_semester'] ?? null, FILTER_VALIDATE_INT);
        $status = $this->string($payload, 'status');

        if ($credits === false || $credits < 1 || $credits > 8 || $recommendedSemester === false || $recommendedSemester < 1 || $recommendedSemester > 8) {
            Response::error('SKS atau semester rekomendasi tidak valid.', 422);
        }

        if (!in_array($status, ['Aktif', 'Arsip'], true)) {
            Response::error('Status mata kuliah tidak valid.', 422);
        }

        if (!$this->repository->lecturerExists($lecturerId)) {
            Response::error('Dosen pengampu tidak ditemukan.', 422);
        }

        return [
            'code' => $code,
            'name' => $name,
            'credits' => $credits,
            'recommended_semester' => $recommendedSemester,
            'lecturer_id' => $lecturerId,
            'status' => $status,
        ];
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
