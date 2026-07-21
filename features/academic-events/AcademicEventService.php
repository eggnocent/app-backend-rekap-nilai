<?php

declare(strict_types=1);

final class AcademicEventService
{
    public function __construct(
        private PDO $connection,
        private AcademicEventRepository $repository,
        private ActivityRepository $activityRepository
    ) {
    }

    public function all(array $user): array
    {
        $startsAt = $this->queryTimestamp('start_at');
        $endsAt = $this->queryTimestamp('end_at');

        if ($startsAt !== null && $endsAt !== null && $startsAt > $endsAt) {
            Response::error('Rentang waktu event tidak valid.', 422);
        }

        if ($user['role'] === 'admin') {
            return $this->repository->all(Request::query('term_id'), null, $startsAt, $endsAt);
        }

        $term = $this->repository->activeTerm();
        if ($term === null) {
            return [];
        }

        return $this->repository->all($term['id'], $user['role'], $startsAt, $endsAt);
    }

    public function create(array $payload, array $user): array
    {
        $event = $this->validatedEvent($payload);

        $this->connection->beginTransaction();

        try {
            $id = $this->repository->create($event, $user['id']);
            $created = $this->repository->find($id);
            $this->activityRepository->create($user['id'], 70, 'create_academic_event', 'Created academic event ' . $created['title'] . '.', $user['role'], $user['email']);
            $this->connection->commit();

            return $created;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function update(string $id, array $payload, array $user): array
    {
        $existing = $this->requiredEvent($id);
        $event = $this->validatedEvent(array_merge($existing, $payload));

        $this->connection->beginTransaction();

        try {
            $this->repository->update($id, $event, $user['id']);
            $updated = $this->repository->find($id);
            $this->activityRepository->create($user['id'], 71, 'update_academic_event', 'Updated academic event ' . $updated['title'] . '.', $user['role'], $user['email']);
            $this->connection->commit();

            return $updated;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function delete(string $id, array $user): void
    {
        $event = $this->requiredEvent($id);
        $this->connection->beginTransaction();

        try {
            $this->repository->delete($id);
            $this->activityRepository->create($user['id'], 72, 'delete_academic_event', 'Deleted academic event ' . $event['title'] . '.', $user['role'], $user['email']);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    private function requiredEvent(string $id): array
    {
        $event = $this->repository->find($id);
        if ($event === null) {
            Response::error('Event akademik tidak ditemukan.', 404);
        }

        return $event;
    }

    private function validatedEvent(array $payload): array
    {
        $termId = $this->requiredString($payload, 'term_id');
        $term = $this->repository->findTerm($termId);
        if ($term === null) {
            Response::error('Semester tidak ditemukan.', 404);
        }

        $startsAt = $this->timestamp($payload['starts_at'] ?? null, 'starts_at');
        $endsAt = $this->timestamp($payload['ends_at'] ?? null, 'ends_at');

        if ($startsAt > $endsAt) {
            Response::error('Waktu selesai event tidak boleh sebelum waktu mulai.', 422);
        }

        $zone = new DateTimeZone('Asia/Jakarta');
        $startDate = (new DateTimeImmutable($startsAt))->setTimezone($zone)->format('Y-m-d');
        $endDate = (new DateTimeImmutable($endsAt))->setTimezone($zone)->format('Y-m-d');
        if ($startDate < $term['start_date'] || $endDate > $term['end_date']) {
            Response::error('Waktu event harus berada dalam rentang semester.', 422);
        }

        $audience = $this->requiredString($payload, 'audience');
        if (!in_array($audience, ['all', 'admin', 'lecturer', 'student'], true)) {
            Response::error('Audiens event tidak valid.', 422);
        }

        return [
            'term_id' => $termId,
            'title' => $this->requiredString($payload, 'title'),
            'description' => $this->optionalString($payload, 'description'),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'location' => $this->optionalString($payload, 'location'),
            'audience' => $audience,
        ];
    }

    private function queryTimestamp(string $key): ?string
    {
        $value = Request::query($key);
        return $value === null ? null : $this->timestamp($value, $key);
    }

    private function timestamp(mixed $value, string $field): string
    {
        if (!is_string($value) || trim($value) === '') {
            Response::error('Field ' . $field . ' wajib diisi.', 422);
        }

        try {
            return (new DateTimeImmutable($value))->format(DateTimeInterface::ATOM);
        } catch (Throwable) {
            Response::error('Field ' . $field . ' tidak valid.', 422);
        }
    }

    private function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            Response::error('Field ' . $key . ' wajib diisi.', 422);
        }

        return trim($value);
    }

    private function optionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            Response::error('Field ' . $key . ' tidak valid.', 422);
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
