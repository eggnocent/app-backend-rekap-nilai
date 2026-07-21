<?php

declare(strict_types=1);

final class AttendanceRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function findClass(string $classId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT c.id, c.lecturer_id, c.status, c.code AS class_code, c.term_id, course.name AS course_name, term.name AS term_name, term.start_date, term.end_date
             FROM classes c
             INNER JOIN courses course ON course.id = c.course_id
             INNER JOIN academic_terms term ON term.id = c.term_id
             WHERE c.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $classId]);
        $class = $statement->fetch();

        return is_array($class) ? $class : null;
    }

    public function findMeeting(string $meetingId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT m.id, m.class_id, m.meeting_date, m.topic, c.lecturer_id, c.status AS class_status, c.code AS class_code, c.term_id, course.name AS course_name, term.name AS term_name, term.start_date, term.end_date
             FROM attendance_meetings m
             INNER JOIN classes c ON c.id = m.class_id
             INNER JOIN courses course ON course.id = c.course_id
             INNER JOIN academic_terms term ON term.id = c.term_id
             WHERE m.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $meetingId]);
        $meeting = $statement->fetch();

        return is_array($meeting) ? $meeting : null;
    }

    public function meetingExists(string $classId, string $meetingDate): bool
    {
        $statement = $this->connection->prepare('SELECT 1 FROM attendance_meetings WHERE class_id = :class_id AND meeting_date = :meeting_date');
        $statement->execute(['class_id' => $classId, 'meeting_date' => $meetingDate]);

        return $statement->fetchColumn() !== false;
    }

    public function createMeeting(string $classId, string $meetingDate, string $topic, string $userId): string
    {
        $statement = $this->connection->prepare(
            'INSERT INTO attendance_meetings (class_id, meeting_date, topic, created_by)
             VALUES (:class_id, :meeting_date, :topic, :created_by)
             RETURNING id'
        );
        $statement->execute([
            'class_id' => $classId,
            'meeting_date' => $meetingDate,
            'topic' => $topic,
            'created_by' => $userId,
        ]);

        return (string) $statement->fetchColumn();
    }

    public function meetingsForClass(string $classId): array
    {
        $statement = $this->connection->prepare(
            'SELECT m.id, m.class_id, m.meeting_date, m.topic, m.created_at,
                    (SELECT COUNT(*) FROM attendance_records r WHERE r.meeting_id = m.id AND r.status = :present) AS present_count,
                    (SELECT COUNT(*) FROM attendance_records r WHERE r.meeting_id = m.id) AS recorded_count
             FROM attendance_meetings m
             WHERE m.class_id = :class_id
             ORDER BY m.meeting_date DESC, m.created_at DESC'
        );
        $statement->execute(['class_id' => $classId, 'present' => 'Hadir']);

        return array_map(fn (array $meeting): array => $this->normalizeMeeting($meeting), $statement->fetchAll());
    }

    public function roster(string $meetingId): array
    {
        $statement = $this->connection->prepare(
            "SELECT e.id AS enrollment_id, student.id AS student_id, student.nim, student_user.name AS student_name,
                    r.id AS record_id, r.status, r.recorded_at,
                    (SELECT COUNT(*)
                     FROM attendance_records totals
                     INNER JOIN attendance_meetings total_meeting ON total_meeting.id = totals.meeting_id
                     WHERE total_meeting.class_id = m.class_id
                       AND totals.enrollment_id = e.id
                       AND totals.status = 'Hadir') AS present_total,
                    (SELECT COUNT(*) FROM attendance_meetings total_meeting WHERE total_meeting.class_id = m.class_id) AS meeting_total
             FROM attendance_meetings m
             INNER JOIN enrollments e ON e.class_id = m.class_id AND e.status = 'Terdaftar'
             INNER JOIN student_profiles student ON student.id = e.student_id
             INNER JOIN users student_user ON student_user.id = student.user_id
             LEFT JOIN attendance_records r ON r.meeting_id = m.id AND r.enrollment_id = e.id
             WHERE m.id = :meeting_id
             ORDER BY student.nim"
        );
        $statement->execute(['meeting_id' => $meetingId]);

        return array_map(function (array $record): array {
            $record['present_total'] = (int) $record['present_total'];
            $record['meeting_total'] = (int) $record['meeting_total'];

            return $record;
        }, $statement->fetchAll());
    }

    public function enrollmentForMeeting(string $meetingId, string $enrollmentId): ?array
    {
        $statement = $this->connection->prepare(
            "SELECT e.id, e.class_id, e.status AS enrollment_status, m.class_id AS meeting_class_id
             FROM attendance_meetings m
             INNER JOIN enrollments e ON e.id = :enrollment_id
             WHERE m.id = :meeting_id
             LIMIT 1"
        );
        $statement->execute(['meeting_id' => $meetingId, 'enrollment_id' => $enrollmentId]);
        $enrollment = $statement->fetch();

        return is_array($enrollment) ? $enrollment : null;
    }

    public function upsertRecord(string $meetingId, string $enrollmentId, string $status, string $userId): void
    {
        $existing = $this->connection->prepare('SELECT id FROM attendance_records WHERE meeting_id = :meeting_id AND enrollment_id = :enrollment_id LIMIT 1');
        $existing->execute(['meeting_id' => $meetingId, 'enrollment_id' => $enrollmentId]);
        $id = $existing->fetchColumn();

        if ($id === false) {
            $statement = $this->connection->prepare(
                'INSERT INTO attendance_records (meeting_id, enrollment_id, status, recorded_at, created_by)
                 VALUES (:meeting_id, :enrollment_id, :status, NOW(), :created_by)'
            );
            $statement->execute([
                'meeting_id' => $meetingId,
                'enrollment_id' => $enrollmentId,
                'status' => $status,
                'created_by' => $userId,
            ]);

            return;
        }

        $statement = $this->connection->prepare(
            'UPDATE attendance_records
             SET status = :status, recorded_at = NOW(), updated_at = NOW(), updated_by = :updated_by
             WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'status' => $status, 'updated_by' => $userId]);
    }

    public function all(?string $termId, ?string $classId, ?string $meetingId): array
    {
        $conditions = [];
        $parameters = [];

        foreach (['term_id' => $termId, 'class_id' => $classId, 'meeting_id' => $meetingId] as $key => $value) {
            if ($value !== null) {
                $conditions[] = $key === 'meeting_id' ? 'm.id = :meeting_id' : ($key === 'term_id' ? 'c.term_id = :term_id' : 'm.class_id = :class_id');
                $parameters[$key] = $value;
            }
        }

        $query =
            "SELECT m.id, m.class_id, m.meeting_date, m.topic, c.term_id, c.code AS class_code, course.name AS course_name,
                    (SELECT COUNT(*) FROM attendance_records r WHERE r.meeting_id = m.id AND r.status = 'Hadir') AS present_count,
                    (SELECT COUNT(*) FROM attendance_records r WHERE r.meeting_id = m.id) AS recorded_count
             FROM attendance_meetings m
             INNER JOIN classes c ON c.id = m.class_id
             INNER JOIN courses course ON course.id = c.course_id";
        if ($conditions !== []) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $query .= ' ORDER BY m.meeting_date DESC, m.created_at DESC';
        $statement = $this->connection->prepare($query);
        $statement->execute($parameters);

        return array_map(fn (array $meeting): array => $this->normalizeMeeting($meeting), $statement->fetchAll());
    }

    public function mine(string $studentId, string $termId): array
    {
        $statement = $this->connection->prepare(
            "SELECT m.id AS meeting_id, m.meeting_date, m.topic, c.id AS class_id, c.code AS class_code, course.code AS course_code, course.name AS course_name,
                    r.id AS record_id, r.status, r.recorded_at
             FROM enrollments e
             INNER JOIN classes c ON c.id = e.class_id
             INNER JOIN courses course ON course.id = c.course_id
             INNER JOIN attendance_meetings m ON m.class_id = c.id
             LEFT JOIN attendance_records r ON r.meeting_id = m.id AND r.enrollment_id = e.id
             WHERE e.student_id = :student_id
               AND e.status = 'Terdaftar'
               AND c.term_id = :term_id
             ORDER BY m.meeting_date DESC, course.code"
        );
        $statement->execute(['student_id' => $studentId, 'term_id' => $termId]);

        return $statement->fetchAll();
    }

    private function normalizeMeeting(array $meeting): array
    {
        $meeting['present_count'] = (int) $meeting['present_count'];
        $meeting['recorded_count'] = (int) $meeting['recorded_count'];

        return $meeting;
    }
}
