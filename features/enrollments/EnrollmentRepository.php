<?php

declare(strict_types=1);

final class EnrollmentRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function activeTerm(): ?array
    {
        $statement = $this->connection->query(
            'SELECT id, name, academic_year, semester
             FROM academic_terms
             WHERE is_active IS TRUE
             ORDER BY start_date DESC NULLS LAST
             LIMIT 1'
        );
        $term = $statement->fetch();

        return is_array($term) ? $term : null;
    }

    public function findStudent(string $studentId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT sp.id, sp.status, sp.nim, u.name, u.email
             FROM student_profiles sp
             INNER JOIN users u ON u.id = sp.user_id
             WHERE sp.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $studentId]);
        $student = $statement->fetch();

        return is_array($student) ? $student : null;
    }

    public function lockClass(string $classId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT c.id, c.term_id, c.course_id, c.capacity, c.status, t.is_active, course.code AS course_code, course.name AS course_name
             FROM classes c
             INNER JOIN academic_terms t ON t.id = c.term_id
             INNER JOIN courses course ON course.id = c.course_id
             WHERE c.id = :id
             FOR UPDATE OF c'
        );
        $statement->execute(['id' => $classId]);
        $class = $statement->fetch();

        return is_array($class) ? $class : null;
    }

    public function activeEnrollmentExists(string $studentId, string $classId): bool
    {
        return $this->exists(
            "SELECT 1 FROM enrollments WHERE student_id = :student_id AND class_id = :class_id AND status = 'Terdaftar'",
            ['student_id' => $studentId, 'class_id' => $classId]
        );
    }

    public function activeCourseEnrollmentExists(string $studentId, string $termId, string $courseId): bool
    {
        return $this->exists(
            "SELECT 1
             FROM enrollments e
             INNER JOIN classes c ON c.id = e.class_id
             WHERE e.student_id = :student_id
               AND e.status = 'Terdaftar'
               AND c.term_id = :term_id
               AND c.course_id = :course_id",
            ['student_id' => $studentId, 'term_id' => $termId, 'course_id' => $courseId]
        );
    }

    public function enrolledCount(string $classId): int
    {
        $statement = $this->connection->prepare("SELECT COUNT(*) FROM enrollments WHERE class_id = :class_id AND status = 'Terdaftar'");
        $statement->execute(['class_id' => $classId]);

        return (int) $statement->fetchColumn();
    }

    public function hasScheduleConflict(string $studentId, string $termId, string $classId): bool
    {
        $statement = $this->connection->prepare(
            "SELECT 1
             FROM enrollments e
             INNER JOIN classes enrolled_class ON enrolled_class.id = e.class_id
             INNER JOIN class_schedules enrolled_schedule ON enrolled_schedule.class_id = enrolled_class.id
             INNER JOIN class_schedules requested_schedule ON requested_schedule.class_id = :class_id
             WHERE e.student_id = :student_id
               AND e.status = 'Terdaftar'
               AND enrolled_class.term_id = :term_id
               AND enrolled_schedule.day_of_week = requested_schedule.day_of_week
               AND enrolled_schedule.start_time < requested_schedule.end_time
               AND requested_schedule.start_time < enrolled_schedule.end_time
             LIMIT 1"
        );
        $statement->execute([
            'student_id' => $studentId,
            'term_id' => $termId,
            'class_id' => $classId,
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function create(string $studentId, string $classId, string $userId): string
    {
        $statement = $this->connection->prepare(
            "INSERT INTO enrollments (student_id, class_id, status, enrolled_at, created_by)
             VALUES (:student_id, :class_id, 'Terdaftar', NOW(), :created_by)
             RETURNING id"
        );
        $statement->execute([
            'student_id' => $studentId,
            'class_id' => $classId,
            'created_by' => $userId,
        ]);

        return (string) $statement->fetchColumn();
    }

    public function find(string $id): ?array
    {
        $query = $this->selectQuery() . ' WHERE e.id = :id ' . $this->groupBy() . ' LIMIT 1';
        $statement = $this->connection->prepare($query);
        $statement->execute(['id' => $id]);
        $enrollment = $statement->fetch();

        return is_array($enrollment) ? $this->normalize($enrollment) : null;
    }

    public function all(?string $studentId, ?string $classId, ?string $status): array
    {
        $conditions = [];
        $parameters = [];

        foreach (['student_id' => $studentId, 'class_id' => $classId, 'status' => $status] as $key => $value) {
            if ($value !== null) {
                $conditions[] = 'e.' . $key . ' = :' . $key;
                $parameters[$key] = $value;
            }
        }

        $query = $this->selectQuery();

        if ($conditions !== []) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $query .= ' ' . $this->groupBy() . ' ORDER BY term.start_date DESC NULLS LAST, course.code, class.code';
        $statement = $this->connection->prepare($query);
        $statement->execute($parameters);

        return $this->normalizeAll($statement->fetchAll());
    }

    public function allForStudent(string $studentId, string $termId): array
    {
        $query = $this->selectQuery() . " WHERE e.student_id = :student_id AND e.status = 'Terdaftar' AND class.term_id = :term_id " . $this->groupBy() . ' ORDER BY course.code, class.code';
        $statement = $this->connection->prepare($query);
        $statement->execute([
            'student_id' => $studentId,
            'term_id' => $termId,
        ]);

        return $this->normalizeAll($statement->fetchAll());
    }

    public function cancel(string $id, string $userId): void
    {
        $statement = $this->connection->prepare(
            "UPDATE enrollments
             SET status = 'Dibatalkan', updated_at = NOW(), updated_by = :updated_by
             WHERE id = :id"
        );
        $statement->execute([
            'id' => $id,
            'updated_by' => $userId,
        ]);
    }

    private function exists(string $query, array $parameters): bool
    {
        $statement = $this->connection->prepare($query);
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    private function selectQuery(): string
    {
        return <<<'SQL'
SELECT
    e.id,
    e.student_id,
    e.class_id,
    e.status,
    e.enrolled_at,
    student.nim AS student_nim,
    student_user.name AS student_name,
    student_user.email AS student_email,
    class.term_id,
    class.code AS class_code,
    class.capacity,
    class.status AS class_status,
    term.name AS term_name,
    term.academic_year,
    term.semester AS term_semester,
    course.id AS course_id,
    course.code AS course_code,
    course.name AS course_name,
    course.credits AS course_credits,
    lecturer.id AS lecturer_id,
    lecturer.nidn AS lecturer_nidn,
    lecturer_user.name AS lecturer_name,
    (SELECT COUNT(*) FROM enrollments enrolled WHERE enrolled.class_id = class.id AND enrolled.status = 'Terdaftar') AS enrolled_count,
    COALESCE(
        json_agg(
            json_build_object(
                'id', schedule.id,
                'day_of_week', schedule.day_of_week,
                'start_time', to_char(schedule.start_time, 'HH24:MI'),
                'end_time', to_char(schedule.end_time, 'HH24:MI'),
                'room', schedule.room
            )
            ORDER BY schedule.day_of_week, schedule.start_time
        ) FILTER (WHERE schedule.id IS NOT NULL),
        '[]'::json
    ) AS schedules
FROM enrollments e
INNER JOIN student_profiles student ON student.id = e.student_id
INNER JOIN users student_user ON student_user.id = student.user_id
INNER JOIN classes class ON class.id = e.class_id
INNER JOIN academic_terms term ON term.id = class.term_id
INNER JOIN courses course ON course.id = class.course_id
INNER JOIN lecturer_profiles lecturer ON lecturer.id = class.lecturer_id
INNER JOIN users lecturer_user ON lecturer_user.id = lecturer.user_id
LEFT JOIN class_schedules schedule ON schedule.class_id = class.id
SQL;
    }

    private function groupBy(): string
    {
        return 'GROUP BY e.id, student.id, student_user.id, class.id, term.id, course.id, lecturer.id, lecturer_user.id';
    }

    private function normalizeAll(array $enrollments): array
    {
        return array_map(fn (array $enrollment): array => $this->normalize($enrollment), $enrollments);
    }

    private function normalize(array $enrollment): array
    {
        $schedules = json_decode($enrollment['schedules'], true);
        $enrollment['schedules'] = is_array($schedules) ? $schedules : [];
        $enrollment['capacity'] = (int) $enrollment['capacity'];
        $enrollment['course_credits'] = (int) $enrollment['course_credits'];
        $enrollment['enrolled_count'] = (int) $enrollment['enrolled_count'];

        return $enrollment;
    }
}
