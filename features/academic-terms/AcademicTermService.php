<?php

declare(strict_types=1);

final class AcademicTermService
{
    public function __construct(
        private PDO $connection,
        private AcademicTermRepository $repository,
        private ActivityRepository $activityRepository
    ) {
    }

    public function active(): array
    {
        $term = $this->repository->active();

        if ($term === null) {
            Response::error('Semester aktif tidak ditemukan.', 404);
        }

        return $term;
    }

    public function all(): array
    {
        return $this->repository->all(Pagination::fromRequest());
    }

    public function create(array $payload, array $user): array
    {
        $term = $this->validatedTerm($payload);

        $this->connection->beginTransaction();

        try {
            $created = $this->repository->create($term, $user['id']);

            if ($term['is_active']) {
                $this->repository->deactivateOthers($created['id'], $user['id']);
            }

            $this->activityRepository->create($user['id'], 10, 'create_academic_term', 'Created academic term ' . $created['name'] . '.', $user['role'], $user['email']);
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
            Response::error('Semester tidak ditemukan.', 404);
        }

        $term = $this->validatedTerm(array_merge($existing, $payload));

        if ((bool) $existing['is_active'] && !$term['is_active']) {
            Response::error('Aktifkan semester lain sebelum menonaktifkan semester ini.', 422);
        }

        $this->connection->beginTransaction();

        try {
            $updated = $this->repository->update($id, $term, $user['id']);

            if ($term['is_active']) {
                $this->repository->deactivateOthers($id, $user['id']);
            }

            $this->activityRepository->create($user['id'], 11, 'update_academic_term', 'Updated academic term ' . $updated['name'] . '.', $user['role'], $user['email']);
            $this->connection->commit();

            return $updated;
        } catch (Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    private function validatedTerm(array $payload): array
    {
        $name = $this->string($payload, 'name');
        $academicYear = $this->string($payload, 'academic_year');
        $semester = $this->string($payload, 'semester');
        $startDate = $this->string($payload, 'start_date');
        $endDate = $this->string($payload, 'end_date');
        $isActive = isset($payload['is_active']) ? filter_var($payload['is_active'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) : false;

        if ($isActive === null || strtotime($startDate) === false || strtotime($endDate) === false || $startDate > $endDate) {
            Response::error('Data semester tidak valid.', 422);
        }

        return [
            'name' => $name,
            'academic_year' => $academicYear,
            'semester' => $semester,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_active' => $isActive,
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
