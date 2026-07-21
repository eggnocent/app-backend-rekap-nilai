<?php

declare(strict_types=1);

final class CourseRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function all(?string $status, ?string $semester, ?string $search, bool $includeArchived): array
    {
        $conditions = [];
        $parameters = [];

        if (!$includeArchived) {
            $conditions[] = "c.status = 'Aktif'";
        } elseif ($status !== null) {
            $conditions[] = 'c.status = :status';
            $parameters['status'] = $status;
        }

        if ($semester !== null) {
            $conditions[] = 'c.recommended_semester = :semester';
            $parameters['semester'] = (int) $semester;
        }

        if ($search !== null) {
            $conditions[] = '(LOWER(c.code) LIKE :search OR LOWER(c.name) LIKE :search OR LOWER(u.name) LIKE :search)';
            $parameters['search'] = '%' . strtolower($search) . '%';
        }

        $query =
            'SELECT
                c.id,
                c.code,
                c.name,
                c.credits,
                c.recommended_semester,
                c.lecturer_id,
                c.status,
                lp.nidn AS lecturer_nidn,
                u.name AS lecturer_name,
                u.email AS lecturer_email
             FROM courses c
             LEFT JOIN lecturer_profiles lp ON lp.id = c.lecturer_id
             LEFT JOIN users u ON u.id = lp.user_id';

        if ($conditions !== []) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $query .= ' ORDER BY c.recommended_semester, c.code';
        $statement = $this->connection->prepare($query);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function find(string $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, code, name, credits, recommended_semester, lecturer_id, status
             FROM courses
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $course = $statement->fetch();

        return is_array($course) ? $course : null;
    }

    public function lecturerExists(string $lecturerId): bool
    {
        $statement = $this->connection->prepare('SELECT 1 FROM lecturer_profiles WHERE id = :id');
        $statement->execute(['id' => $lecturerId]);

        return $statement->fetchColumn() !== false;
    }

    public function create(array $course, string $userId): array
    {
        $statement = $this->connection->prepare(
            'INSERT INTO courses (code, name, credits, recommended_semester, lecturer_id, status, created_by)
             VALUES (:code, :name, :credits, :recommended_semester, :lecturer_id, :status, :created_by)
             RETURNING id, code, name, credits, recommended_semester, lecturer_id, status'
        );
        $statement->execute([
            'code' => $course['code'],
            'name' => $course['name'],
            'credits' => $course['credits'],
            'recommended_semester' => $course['recommended_semester'],
            'lecturer_id' => $course['lecturer_id'],
            'status' => $course['status'],
            'created_by' => $userId,
        ]);

        return $statement->fetch();
    }

    public function update(string $id, array $course, string $userId): array
    {
        $statement = $this->connection->prepare(
            'UPDATE courses
             SET code = :code,
                 name = :name,
                 credits = :credits,
                 recommended_semester = :recommended_semester,
                 lecturer_id = :lecturer_id,
                 status = :status,
                 updated_at = NOW(),
                 updated_by = :updated_by
             WHERE id = :id
             RETURNING id, code, name, credits, recommended_semester, lecturer_id, status'
        );
        $statement->execute([
            'id' => $id,
            'code' => $course['code'],
            'name' => $course['name'],
            'credits' => $course['credits'],
            'recommended_semester' => $course['recommended_semester'],
            'lecturer_id' => $course['lecturer_id'],
            'status' => $course['status'],
            'updated_by' => $userId,
        ]);

        return $statement->fetch();
    }
}
