<?php

declare(strict_types=1);

final class DashboardRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function activeTerm(): ?array
    {
        $term = $this->connection->query(
            'SELECT id, name, academic_year, semester, start_date, end_date
             FROM academic_terms
             WHERE is_active IS TRUE
             ORDER BY start_date DESC NULLS LAST
             LIMIT 1'
        )->fetch();

        return is_array($term) ? $term : null;
    }

    public function admin(string $termId): array
    {
        return [
            'metrics' => $this->adminMetrics($termId),
            'grade_distribution' => $this->gradeDistribution($termId),
            'performance' => $this->performance($termId),
            'classes' => $this->classes($termId),
            'activities' => $this->activities(),
            'upcoming_events' => $this->upcomingEvents($termId),
        ];
    }

    public function lecturer(string $termId, string $lecturerId, int $dayOfWeek): array
    {
        $classes = $this->classes($termId, $lecturerId);

        return [
            'metrics' => $this->lecturerMetrics($termId, $lecturerId),
            'classes' => $classes,
            'today_schedule' => $this->todaySchedule($termId, $lecturerId, $dayOfWeek),
            'upcoming_events' => $this->upcomingEvents($termId, 'lecturer'),
        ];
    }

    public function student(string $termId, string $studentId): array
    {
        return [
            'metrics' => $this->studentMetrics($termId, $studentId),
            'attendance' => $this->studentAttendance($termId, $studentId),
            'classes' => $this->studentClasses($termId, $studentId),
            'published_grades' => $this->publishedGrades($termId, $studentId),
            'upcoming_events' => $this->upcomingEvents($termId, 'student'),
        ];
    }

    private function adminMetrics(string $termId): array
    {
        $statement = $this->connection->prepare(
            "SELECT
                (SELECT COUNT(*) FROM student_profiles WHERE status = 'active') AS students,
                (SELECT COUNT(DISTINCT c.course_id) FROM classes c WHERE c.term_id = :term_id AND c.status = 'Aktif') AS active_courses,
                (SELECT COUNT(*) FROM grades g INNER JOIN enrollments e ON e.id = g.enrollment_id INNER JOIN classes c ON c.id = e.class_id WHERE c.term_id = :term_id) AS grade_records,
                (SELECT COUNT(*) FROM classes c WHERE c.term_id = :term_id AND c.status = 'Aktif') AS active_classes"
        );
        $statement->execute(['term_id' => $termId]);

        return $this->integerFields($statement->fetch() ?: [], ['students', 'active_courses', 'grade_records', 'active_classes']);
    }

    private function gradeDistribution(string $termId): array
    {
        $statement = $this->connection->prepare(
            "SELECT letter_grade, COUNT(*) AS total
             FROM grades g
             INNER JOIN enrollments e ON e.id = g.enrollment_id
             INNER JOIN classes c ON c.id = e.class_id
             WHERE c.term_id = :term_id
               AND g.letter_grade IN ('A', 'B', 'C', 'D', 'E')
             GROUP BY g.letter_grade
             ORDER BY g.letter_grade"
        );
        $statement->execute(['term_id' => $termId]);

        return array_map(fn (array $row): array => ['letter_grade' => $row['letter_grade'], 'total' => (int) $row['total']], $statement->fetchAll());
    }

    private function performance(string $termId): array
    {
        $statement = $this->connection->prepare(
            "SELECT to_char(date_trunc('month', COALESCE(g.updated_at, g.created_at)), 'YYYY-MM') AS month, AVG(g.final_score) AS average_score
             FROM grades g
             INNER JOIN enrollments e ON e.id = g.enrollment_id
             INNER JOIN classes c ON c.id = e.class_id
             WHERE c.term_id = :term_id
               AND g.final_score IS NOT NULL
             GROUP BY date_trunc('month', COALESCE(g.updated_at, g.created_at))
             ORDER BY date_trunc('month', COALESCE(g.updated_at, g.created_at)) ASC"
        );
        $statement->execute(['term_id' => $termId]);

        return array_map(fn (array $row): array => [
            'month' => $row['month'],
            'average_score' => round((float) $row['average_score'], 2),
        ], $statement->fetchAll());
    }

    private function classes(string $termId, ?string $lecturerId = null): array
    {
        $query =
            "SELECT c.id, c.code AS class_code, c.capacity, course.code AS course_code, course.name AS course_name, course.credits,
                    lecturer.id AS lecturer_id, lecturer_user.name AS lecturer_name,
                    COUNT(DISTINCT e.id) FILTER (WHERE e.status = 'Terdaftar') AS enrolled_count,
                    COUNT(DISTINCT g.id) FILTER (WHERE e.status = 'Terdaftar') AS graded_count,
                    COALESCE(json_agg(DISTINCT jsonb_build_object('day_of_week', s.day_of_week, 'start_time', to_char(s.start_time, 'HH24:MI'), 'end_time', to_char(s.end_time, 'HH24:MI'), 'room', s.room) ORDER BY jsonb_build_object('day_of_week', s.day_of_week, 'start_time', to_char(s.start_time, 'HH24:MI'), 'end_time', to_char(s.end_time, 'HH24:MI'), 'room', s.room)) FILTER (WHERE s.id IS NOT NULL), '[]'::json) AS schedules
             FROM classes c
             INNER JOIN courses course ON course.id = c.course_id
             INNER JOIN lecturer_profiles lecturer ON lecturer.id = c.lecturer_id
             INNER JOIN users lecturer_user ON lecturer_user.id = lecturer.user_id
             LEFT JOIN enrollments e ON e.class_id = c.id
             LEFT JOIN grades g ON g.enrollment_id = e.id
             LEFT JOIN class_schedules s ON s.class_id = c.id
             WHERE c.term_id = :term_id
               AND c.status = 'Aktif'";
        $parameters = ['term_id' => $termId];

        if ($lecturerId !== null) {
            $query .= ' AND c.lecturer_id = :lecturer_id';
            $parameters['lecturer_id'] = $lecturerId;
        }

        $query .= ' GROUP BY c.id, course.id, lecturer.id, lecturer_user.id ORDER BY course.code, c.code';
        $statement = $this->connection->prepare($query);
        $statement->execute($parameters);

        return array_map(function (array $row): array {
            $enrolledCount = (int) $row['enrolled_count'];
            $gradedCount = (int) $row['graded_count'];

            return [
                'id' => $row['id'],
                'class_code' => $row['class_code'],
                'course_code' => $row['course_code'],
                'course_name' => $row['course_name'],
                'credits' => (int) $row['credits'],
                'lecturer_id' => $row['lecturer_id'],
                'lecturer_name' => $row['lecturer_name'],
                'capacity' => (int) $row['capacity'],
                'enrolled_count' => $enrolledCount,
                'graded_count' => $gradedCount,
                'grade_progress' => $enrolledCount === 0 ? 0 : round($gradedCount / $enrolledCount * 100, 2),
                'schedules' => $this->json($row['schedules']),
            ];
        }, $statement->fetchAll());
    }

    private function activities(): array
    {
        $statement = $this->connection->query(
            'SELECT id, user_id, activity_type, activity, activity_string, role, email, created_at
             FROM activity_log
             ORDER BY created_at DESC
             LIMIT 4'
        );

        return array_map(function (array $row): array {
            $row['activity_type'] = (int) $row['activity_type'];
            return $row;
        }, $statement->fetchAll());
    }

    private function upcomingEvents(string $termId, ?string $role = null): array
    {
        $query =
            'SELECT id, title, description, starts_at, ends_at, location, audience
             FROM academic_events
             WHERE term_id = :term_id
               AND ends_at >= NOW()';
        $parameters = ['term_id' => $termId];

        if ($role !== null) {
            $query .= " AND audience IN ('all', :role)";
            $parameters['role'] = $role;
        }

        $query .= ' ORDER BY starts_at ASC, created_at DESC LIMIT 4';
        $statement = $this->connection->prepare($query);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    private function lecturerMetrics(string $termId, string $lecturerId): array
    {
        $statement = $this->connection->prepare(
            "SELECT COUNT(DISTINCT c.id) AS classes,
                    COUNT(DISTINCT e.id) FILTER (WHERE e.status = 'Terdaftar') AS students,
                    COUNT(DISTINCT g.id) FILTER (WHERE e.status = 'Terdaftar') AS graded_count,
                    AVG(g.final_score) AS average_final_score
             FROM classes c
             LEFT JOIN enrollments e ON e.class_id = c.id
             LEFT JOIN grades g ON g.enrollment_id = e.id
             WHERE c.term_id = :term_id
               AND c.lecturer_id = :lecturer_id
               AND c.status = 'Aktif'"
        );
        $statement->execute(['term_id' => $termId, 'lecturer_id' => $lecturerId]);
        $row = $statement->fetch() ?: [];
        $students = (int) ($row['students'] ?? 0);
        $graded = (int) ($row['graded_count'] ?? 0);

        return [
            'classes' => (int) ($row['classes'] ?? 0),
            'students' => $students,
            'grade_completion' => $students === 0 ? 0 : round($graded / $students * 100, 2),
            'average_final_score' => $row['average_final_score'] === null ? null : (float) $row['average_final_score'],
        ];
    }

    private function todaySchedule(string $termId, string $lecturerId, int $dayOfWeek): array
    {
        $statement = $this->connection->prepare(
            "SELECT c.id AS class_id, c.code AS class_code, course.code AS course_code, course.name AS course_name,
                    to_char(s.start_time, 'HH24:MI') AS start_time, to_char(s.end_time, 'HH24:MI') AS end_time, s.room
             FROM class_schedules s
             INNER JOIN classes c ON c.id = s.class_id
             INNER JOIN courses course ON course.id = c.course_id
             WHERE c.term_id = :term_id
               AND c.lecturer_id = :lecturer_id
               AND c.status = 'Aktif'
               AND s.day_of_week = :day_of_week
             ORDER BY s.start_time, c.code"
        );
        $statement->execute(['term_id' => $termId, 'lecturer_id' => $lecturerId, 'day_of_week' => $dayOfWeek]);

        return $statement->fetchAll();
    }

    private function studentMetrics(string $termId, string $studentId): array
    {
        $statement = $this->connection->prepare(
            "SELECT COUNT(*) AS active_classes,
                    COALESCE(SUM(credits), 0) AS total_credits,
                    COUNT(*) FILTER (WHERE grade_status = 'published') AS published_grades
             FROM (
                 SELECT e.id, course.credits, g.status AS grade_status
                 FROM enrollments e
                 INNER JOIN classes c ON c.id = e.class_id
                 INNER JOIN courses course ON course.id = c.course_id
                 LEFT JOIN grades g ON g.enrollment_id = e.id
                 WHERE e.student_id = :student_id
                   AND e.status = 'Terdaftar'
                   AND c.term_id = :term_id
                   AND c.status = 'Aktif'
             ) AS active_enrollments"
        );
        $statement->execute(['term_id' => $termId, 'student_id' => $studentId]);

        return $this->integerFields($statement->fetch() ?: [], ['active_classes', 'total_credits', 'published_grades']);
    }

    private function studentAttendance(string $termId, string $studentId): array
    {
        $statement = $this->connection->prepare(
            "SELECT COUNT(DISTINCT m.id) AS meetings,
                    COUNT(DISTINCT m.id) FILTER (WHERE r.status = 'Hadir') AS present,
                    COUNT(DISTINCT m.id) FILTER (WHERE r.id IS NULL) AS unrecorded
             FROM enrollments e
             INNER JOIN classes c ON c.id = e.class_id
             INNER JOIN attendance_meetings m ON m.class_id = c.id
             LEFT JOIN attendance_records r ON r.meeting_id = m.id AND r.enrollment_id = e.id
             WHERE e.student_id = :student_id
               AND e.status = 'Terdaftar'
               AND c.term_id = :term_id
               AND c.status = 'Aktif'"
        );
        $statement->execute(['term_id' => $termId, 'student_id' => $studentId]);

        return $this->integerFields($statement->fetch() ?: [], ['meetings', 'present', 'unrecorded']);
    }

    private function studentClasses(string $termId, string $studentId): array
    {
        $statement = $this->connection->prepare(
            "SELECT c.id AS class_id, c.code AS class_code, course.code AS course_code, course.name AS course_name, course.credits,
                    lecturer.id AS lecturer_id, lecturer_user.name AS lecturer_name,
                    COALESCE(json_agg(json_build_object('day_of_week', s.day_of_week, 'start_time', to_char(s.start_time, 'HH24:MI'), 'end_time', to_char(s.end_time, 'HH24:MI'), 'room', s.room) ORDER BY s.day_of_week, s.start_time) FILTER (WHERE s.id IS NOT NULL), '[]'::json) AS schedules
             FROM enrollments e
             INNER JOIN classes c ON c.id = e.class_id
             INNER JOIN courses course ON course.id = c.course_id
             INNER JOIN lecturer_profiles lecturer ON lecturer.id = c.lecturer_id
             INNER JOIN users lecturer_user ON lecturer_user.id = lecturer.user_id
             LEFT JOIN class_schedules s ON s.class_id = c.id
             WHERE e.student_id = :student_id
               AND e.status = 'Terdaftar'
               AND c.term_id = :term_id
               AND c.status = 'Aktif'
             GROUP BY c.id, course.id, lecturer.id, lecturer_user.id
             ORDER BY course.code, c.code"
        );
        $statement->execute(['term_id' => $termId, 'student_id' => $studentId]);

        return array_map(function (array $row): array {
            $row['credits'] = (int) $row['credits'];
            $row['schedules'] = $this->json($row['schedules']);
            return $row;
        }, $statement->fetchAll());
    }

    private function publishedGrades(string $termId, string $studentId): array
    {
        $statement = $this->connection->prepare(
            "SELECT g.id, c.id AS class_id, c.code AS class_code, course.code AS course_code, course.name AS course_name, course.credits,
                    g.final_score, g.letter_grade, g.published_at
             FROM grades g
             INNER JOIN enrollments e ON e.id = g.enrollment_id
             INNER JOIN classes c ON c.id = e.class_id
             INNER JOIN courses course ON course.id = c.course_id
             WHERE e.student_id = :student_id
               AND e.status = 'Terdaftar'
               AND c.term_id = :term_id
               AND g.status = 'published'
             ORDER BY course.code, c.code"
        );
        $statement->execute(['term_id' => $termId, 'student_id' => $studentId]);

        return array_map(function (array $row): array {
            $row['credits'] = (int) $row['credits'];
            $row['final_score'] = (float) $row['final_score'];
            return $row;
        }, $statement->fetchAll());
    }

    private function integerFields(array $row, array $fields): array
    {
        foreach ($fields as $field) {
            $row[$field] = (int) ($row[$field] ?? 0);
        }

        return $row;
    }

    private function json(string $value): array
    {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
