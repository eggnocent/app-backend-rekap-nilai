<?php

declare(strict_types=1);

final class ClassRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function all(?string $termId, ?string $courseId, ?string $status, ?string $lecturerId): array
    {
        $conditions = [];
        $parameters = [];

        foreach (['term_id' => $termId, 'course_id' => $courseId, 'status' => $status, 'lecturer_id' => $lecturerId] as $key => $value) {
            if ($value !== null) {
                $conditions[] = 'c.' . $key . ' = :' . $key;
                $parameters[$key] = $value;
            }
        }

        $query = $this->selectQuery();

        if ($conditions !== []) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $query .= ' GROUP BY c.id, t.id, course.id, lp.id, lecturer.id ORDER BY t.start_date DESC NULLS LAST, c.code';
        $statement = $this->connection->prepare($query);
        $statement->execute($parameters);

        return $this->normalizeAll($statement->fetchAll());
    }

    public function find(string $id): ?array
    {
        $statement = $this->connection->prepare(
            $this->selectQuery() .
            ' WHERE c.id = :id
              GROUP BY c.id, t.id, course.id, lp.id, lecturer.id
              LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $class = $statement->fetch();

        return is_array($class) ? $this->normalize($class) : null;
    }

    public function studentHasActiveEnrollment(string $studentId, string $classId): bool
    {
        $statement = $this->connection->prepare(
            "SELECT 1 FROM enrollments WHERE student_id = :student_id AND class_id = :class_id AND status = 'Terdaftar'"
        );
        $statement->execute(['student_id' => $studentId, 'class_id' => $classId]);

        return $statement->fetchColumn() !== false;
    }

    public function termExists(string $termId): bool
    {
        return $this->exists('SELECT 1 FROM academic_terms WHERE id = :id', $termId);
    }

    public function activeCourseExists(string $courseId): bool
    {
        $statement = $this->connection->prepare("SELECT 1 FROM courses WHERE id = :id AND status = 'Aktif'");
        $statement->execute(['id' => $courseId]);

        return $statement->fetchColumn() !== false;
    }

    public function lecturerExists(string $lecturerId): bool
    {
        return $this->exists('SELECT 1 FROM lecturer_profiles WHERE id = :id', $lecturerId);
    }

    public function codeExists(string $termId, string $code, ?string $exceptId): bool
    {
        $query = 'SELECT 1 FROM classes WHERE term_id = :term_id AND LOWER(code) = LOWER(:code)';
        $parameters = [
            'term_id' => $termId,
            'code' => $code,
        ];

        if ($exceptId !== null) {
            $query .= ' AND id <> :except_id';
            $parameters['except_id'] = $exceptId;
        }

        $statement = $this->connection->prepare($query);
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    public function scheduleConflicts(string $termId, string $lecturerId, array $schedules, ?string $exceptId): bool
    {
        foreach ($schedules as $schedule) {
            $query =
                "SELECT 1
                 FROM classes c
                 INNER JOIN class_schedules s ON s.class_id = c.id
                 WHERE c.term_id = :term_id
                   AND c.status = 'Aktif'
                   AND s.day_of_week = :day_of_week
                   AND s.start_time < :end_time
                   AND :start_time < s.end_time
                   AND (c.lecturer_id = :lecturer_id OR s.room = :room)";
            $parameters = [
                'term_id' => $termId,
                'day_of_week' => $schedule['day_of_week'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'lecturer_id' => $lecturerId,
                'room' => $schedule['room'],
            ];

            if ($exceptId !== null) {
                $query .= ' AND c.id <> :except_id';
                $parameters['except_id'] = $exceptId;
            }

            $statement = $this->connection->prepare($query);
            $statement->execute($parameters);

            if ($statement->fetchColumn() !== false) {
                return true;
            }
        }

        return false;
    }

    public function create(array $class, string $userId): string
    {
        $statement = $this->connection->prepare(
            'INSERT INTO classes (term_id, course_id, lecturer_id, code, capacity, status, created_by)
             VALUES (:term_id, :course_id, :lecturer_id, :code, :capacity, :status, :created_by)
             RETURNING id'
        );
        $statement->execute([
            'term_id' => $class['term_id'],
            'course_id' => $class['course_id'],
            'lecturer_id' => $class['lecturer_id'],
            'code' => $class['code'],
            'capacity' => $class['capacity'],
            'status' => $class['status'],
            'created_by' => $userId,
        ]);

        return $statement->fetchColumn();
    }

    public function update(string $id, array $class, string $userId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE classes
             SET term_id = :term_id,
                 course_id = :course_id,
                 lecturer_id = :lecturer_id,
                 code = :code,
                 capacity = :capacity,
                 updated_at = NOW(),
                 updated_by = :updated_by
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'term_id' => $class['term_id'],
            'course_id' => $class['course_id'],
            'lecturer_id' => $class['lecturer_id'],
            'code' => $class['code'],
            'capacity' => $class['capacity'],
            'updated_by' => $userId,
        ]);
    }

    public function replaceSchedules(string $classId, array $schedules, string $userId): void
    {
        $delete = $this->connection->prepare('DELETE FROM class_schedules WHERE class_id = :class_id');
        $delete->execute(['class_id' => $classId]);
        $insert = $this->connection->prepare(
            'INSERT INTO class_schedules (class_id, day_of_week, start_time, end_time, room, created_by)
             VALUES (:class_id, :day_of_week, :start_time, :end_time, :room, :created_by)'
        );

        foreach ($schedules as $schedule) {
            $insert->execute([
                'class_id' => $classId,
                'day_of_week' => $schedule['day_of_week'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'room' => $schedule['room'],
                'created_by' => $userId,
            ]);
        }
    }

    public function close(string $id, string $userId): void
    {
        $statement = $this->connection->prepare(
            "UPDATE classes
             SET status = 'Ditutup', updated_at = NOW(), updated_by = :updated_by
             WHERE id = :id"
        );
        $statement->execute([
            'id' => $id,
            'updated_by' => $userId,
        ]);
    }

    private function exists(string $query, string $id): bool
    {
        $statement = $this->connection->prepare($query);
        $statement->execute(['id' => $id]);

        return $statement->fetchColumn() !== false;
    }

    private function selectQuery(): string
    {
        return <<<'SQL'
SELECT
    c.id,
    c.term_id,
    c.course_id,
    c.lecturer_id,
    c.code,
    c.capacity,
    c.status,
    t.name AS term_name,
    t.academic_year,
    t.semester AS term_semester,
    course.code AS course_code,
    course.name AS course_name,
    course.credits AS course_credits,
    lp.nidn AS lecturer_nidn,
    lecturer.name AS lecturer_name,
    COALESCE(
        json_agg(
            json_build_object(
                'id', s.id,
                'day_of_week', s.day_of_week,
                'start_time', to_char(s.start_time, 'HH24:MI'),
                'end_time', to_char(s.end_time, 'HH24:MI'),
                'room', s.room
            )
            ORDER BY s.day_of_week, s.start_time
        ) FILTER (WHERE s.id IS NOT NULL),
        '[]'::json
    ) AS schedules
FROM classes c
INNER JOIN academic_terms t ON t.id = c.term_id
INNER JOIN courses course ON course.id = c.course_id
INNER JOIN lecturer_profiles lp ON lp.id = c.lecturer_id
INNER JOIN users lecturer ON lecturer.id = lp.user_id
LEFT JOIN class_schedules s ON s.class_id = c.id
SQL;
    }

    private function normalizeAll(array $classes): array
    {
        return array_map(fn (array $class): array => $this->normalize($class), $classes);
    }

    private function normalize(array $class): array
    {
        $schedules = json_decode($class['schedules'], true);
        $class['schedules'] = is_array($schedules) ? $schedules : [];
        $class['capacity'] = (int) $class['capacity'];
        $class['course_credits'] = (int) $class['course_credits'];

        return $class;
    }
}
