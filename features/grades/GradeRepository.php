<?php

declare(strict_types=1);

final class GradeRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function findEnrollment(string $enrollmentId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT e.id, e.student_id, e.class_id, e.status AS enrollment_status, c.lecturer_id, c.code AS class_code, c.term_id, course.code AS course_code, course.name AS course_name, student.nim, student_user.name AS student_name
             FROM enrollments e
             INNER JOIN classes c ON c.id = e.class_id
             INNER JOIN courses course ON course.id = c.course_id
             INNER JOIN student_profiles student ON student.id = e.student_id
             INNER JOIN users student_user ON student_user.id = student.user_id
             WHERE e.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $enrollmentId]);
        $enrollment = $statement->fetch();

        return is_array($enrollment) ? $enrollment : null;
    }

    public function findByEnrollment(string $enrollmentId): ?array
    {
        $statement = $this->connection->prepare($this->selectQuery() . ' WHERE g.enrollment_id = :enrollment_id LIMIT 1');
        $statement->execute(['enrollment_id' => $enrollmentId]);
        $grade = $statement->fetch();

        return is_array($grade) ? $this->normalize($grade) : null;
    }

    public function find(string $id): ?array
    {
        $statement = $this->connection->prepare($this->selectQuery() . ' WHERE g.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $grade = $statement->fetch();

        return is_array($grade) ? $this->normalize($grade) : null;
    }

    public function roster(string $classId): array
    {
        $statement = $this->connection->prepare(
            "SELECT
                e.id AS enrollment_id,
                e.status AS enrollment_status,
                student.id AS student_id,
                student.nim,
                student_user.name AS student_name,
                g.id AS grade_id,
                (SELECT ROUND(AVG(ms.score), 2) FROM meeting_scores ms WHERE ms.enrollment_id = e.id) AS daily_score,
                (SELECT CASE WHEN COUNT(m.id) = 0 THEN NULL
                             ELSE ROUND(COUNT(*) FILTER (WHERE r.status = 'Hadir')::numeric / COUNT(m.id) * 100, 2)
                        END
                 FROM attendance_meetings m
                 LEFT JOIN attendance_records r ON r.meeting_id = m.id AND r.enrollment_id = e.id
                 WHERE m.class_id = e.class_id) AS attendance_score,
                g.midterm_score,
                g.final_exam_score,
                g.final_score,
                g.letter_grade,
                g.status AS grade_status,
                (SELECT history.note
                 FROM grade_status_history history
                 WHERE history.grade_id = g.id
                   AND history.to_status = 'returned'
                 ORDER BY history.created_at DESC
                 LIMIT 1) AS return_note
             FROM enrollments e
             INNER JOIN student_profiles student ON student.id = e.student_id
             INNER JOIN users student_user ON student_user.id = student.user_id
             LEFT JOIN grades g ON g.enrollment_id = e.id
             WHERE e.class_id = :class_id
               AND e.status = 'Terdaftar'
             ORDER BY student.nim"
        );
        $statement->execute(['class_id' => $classId]);

        return array_map(function (array $row): array {
            foreach (['daily_score', 'attendance_score', 'midterm_score', 'final_exam_score', 'final_score'] as $field) {
                $row[$field] = $row[$field] === null ? null : (float) $row[$field];
            }

            return $row;
        }, $statement->fetchAll());
    }

    public function all(?string $status, ?string $classId, ?string $termId, ?string $studentId, Pagination $pagination): array
    {
        $conditions = [];
        $parameters = [];

        foreach (['status' => $status, 'class_id' => $classId, 'term_id' => $termId] as $key => $value) {
            if ($value !== null) {
                $conditions[] = $key === 'status' ? 'g.status = :status' : 'c.' . $key . ' = :' . $key;
                $parameters[$key] = $value;
            }
        }

        if ($studentId !== null) {
            $conditions[] = 'e.student_id = :student_id';
            $parameters['student_id'] = $studentId;
        }


        $query = $this->selectQuery();

        if ($conditions !== []) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $total = Pagination::total($this->connection, $query, $parameters);
        $statement = $this->connection->prepare(
            $pagination->apply($query . ' ORDER BY term.start_date DESC NULLS LAST, course.code, student.nim')
        );
        $statement->execute($parameters);

        return $pagination->envelope($this->normalizeAll($statement->fetchAll()), $total);
    }

    public function mine(string $studentId, string $termId): array
    {
        $statement = $this->connection->prepare(
            $this->selectQuery() . ' WHERE e.student_id = :student_id AND c.term_id = :term_id ORDER BY course.code, c.code'
        );
        $statement->execute([
            'student_id' => $studentId,
            'term_id' => $termId,
        ]);

        return $this->normalizeAll($statement->fetchAll());
    }

    public function transcript(string $studentId): array
    {
        $statement = $this->connection->prepare(
            $this->selectQuery() . " WHERE e.student_id = :student_id AND g.status = 'published' ORDER BY term.start_date DESC NULLS LAST, course.code, c.code"
        );
        $statement->execute(['student_id' => $studentId]);

        return $this->normalizeAll($statement->fetchAll());
    }

    public function create(string $enrollmentId, array $scores, string $userId): string
    {
        $statement = $this->connection->prepare(
            "INSERT INTO grades (enrollment_id, daily_score, attendance_score, midterm_score, final_exam_score, final_score, letter_grade, status, created_by)
             VALUES (:enrollment_id, :daily_score, :attendance_score, :midterm_score, :final_exam_score, :final_score, :letter_grade, 'draft', :created_by)
             RETURNING id"
        );
        $statement->execute([
            'enrollment_id' => $enrollmentId,
            'daily_score' => $scores['daily_score'],
            'attendance_score' => $scores['attendance_score'],
            'midterm_score' => $scores['midterm_score'],
            'final_exam_score' => $scores['final_exam_score'],
            'final_score' => $scores['final_score'],
            'letter_grade' => $scores['letter_grade'],
            'created_by' => $userId,
        ]);

        return (string) $statement->fetchColumn();
    }

    public function updateScores(string $id, array $scores, string $userId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE grades
             SET daily_score = :daily_score,
                 attendance_score = :attendance_score,
                 midterm_score = :midterm_score,
                 final_exam_score = :final_exam_score,
                 final_score = :final_score,
                 letter_grade = :letter_grade,
                 updated_at = NOW(),
                 updated_by = :updated_by
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'daily_score' => $scores['daily_score'],
            'attendance_score' => $scores['attendance_score'],
            'midterm_score' => $scores['midterm_score'],
            'final_exam_score' => $scores['final_exam_score'],
            'final_score' => $scores['final_score'],
            'letter_grade' => $scores['letter_grade'],
            'updated_by' => $userId,
        ]);
    }

    public function transition(string $id, string $status, string $userId): void
    {
        $timestamp = match ($status) {
            'submitted' => 'submitted_at = NOW(),',
            'verified' => 'verified_at = NOW(),',
            'published' => 'published_at = NOW(),',
            default => '',
        };
        $statement = $this->connection->prepare(
            "UPDATE grades
             SET status = :status,
                 $timestamp
                 updated_at = NOW(),
                 updated_by = :updated_by
             WHERE id = :id"
        );
        $statement->execute([
            'id' => $id,
            'status' => $status,
            'updated_by' => $userId,
        ]);
    }

    public function addHistory(string $gradeId, ?string $fromStatus, string $toStatus, ?string $note, string $userId): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO grade_status_history (grade_id, from_status, to_status, note, created_by)
             VALUES (:grade_id, :from_status, :to_status, :note, :created_by)'
        );
        $statement->execute([
            'grade_id' => $gradeId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'created_by' => $userId,
        ]);
    }

    /** Pertemuan + kelas + dosen pengampu, untuk otorisasi input skor. */
    public function meeting(string $meetingId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT m.id, m.class_id, m.meeting_date, m.topic, c.lecturer_id, c.code AS class_code, course.name AS course_name
             FROM attendance_meetings m
             INNER JOIN classes c ON c.id = m.class_id
             INNER JOIN courses course ON course.id = c.course_id
             WHERE m.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $meetingId]);
        $meeting = $statement->fetch();

        return is_array($meeting) ? $meeting : null;
    }

    /** Mahasiswa satu pertemuan beserta skornya (untuk halaman Nilai Harian). */
    public function meetingScoreRoster(string $meetingId): array
    {
        $statement = $this->connection->prepare(
            "SELECT e.id AS enrollment_id, student.nim, student_user.name AS student_name, ms.score
             FROM attendance_meetings m
             INNER JOIN enrollments e ON e.class_id = m.class_id AND e.status = 'Terdaftar'
             INNER JOIN student_profiles student ON student.id = e.student_id
             INNER JOIN users student_user ON student_user.id = student.user_id
             LEFT JOIN meeting_scores ms ON ms.meeting_id = m.id AND ms.enrollment_id = e.id
             WHERE m.id = :meeting_id
             ORDER BY student.nim"
        );
        $statement->execute(['meeting_id' => $meetingId]);

        return array_map(function (array $row): array {
            $row['score'] = $row['score'] === null ? null : (float) $row['score'];
            return $row;
        }, $statement->fetchAll());
    }

    /** Simpan/ubah skor beberapa mahasiswa untuk satu pertemuan (upsert). */
    public function saveMeetingScores(string $meetingId, array $entries, string $userId): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO meeting_scores (meeting_id, enrollment_id, score, created_by)
             VALUES (:meeting_id, :enrollment_id, :score, :created_by)
             ON CONFLICT (meeting_id, enrollment_id)
             DO UPDATE SET score = EXCLUDED.score, updated_at = NOW(), updated_by = :updated_by'
        );
        foreach ($entries as $entry) {
            $statement->execute([
                'meeting_id' => $meetingId,
                'enrollment_id' => $entry['enrollment_id'],
                'score' => $entry['score'],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }

    /** Rata-rata skor pertemuan yang sudah dinilai (yang belum dikecualikan). */
    public function computeDailyScore(string $enrollmentId): ?float
    {
        $statement = $this->connection->prepare(
            'SELECT ROUND(AVG(score), 2) FROM meeting_scores WHERE enrollment_id = :id'
        );
        $statement->execute(['id' => $enrollmentId]);
        $value = $statement->fetchColumn();

        return $value === null || $value === false ? null : (float) $value;
    }

    /** Skor kehadiran = (jumlah Hadir ÷ jumlah pertemuan kelas) × 100. */
    public function computeAttendanceScore(string $enrollmentId): ?float
    {
        $statement = $this->connection->prepare(
            "SELECT CASE WHEN COUNT(m.id) = 0 THEN NULL
                         ELSE ROUND(COUNT(*) FILTER (WHERE r.status = 'Hadir')::numeric / COUNT(m.id) * 100, 2)
                    END
             FROM attendance_meetings m
             LEFT JOIN attendance_records r ON r.meeting_id = m.id AND r.enrollment_id = :id
             WHERE m.class_id = (SELECT class_id FROM enrollments WHERE id = :id)"
        );
        $statement->execute(['id' => $enrollmentId]);
        $value = $statement->fetchColumn();

        return $value === null || $value === false ? null : (float) $value;
    }

    private function selectQuery(): string
    {
        return <<<'SQL'
SELECT
    g.id,
    g.enrollment_id,
    g.daily_score,
    g.attendance_score,
    g.midterm_score,
    g.final_exam_score,
    g.final_score,
    g.letter_grade,
    g.status,
    g.submitted_at,
    g.verified_at,
    g.published_at,
    e.student_id,
    e.class_id,
    e.status AS enrollment_status,
    student.nim,
    student_user.name AS student_name,
    c.term_id,
    c.lecturer_id,
    c.code AS class_code,
    term.name AS term_name,
    term.academic_year,
    term.semester AS term_semester,
    course.code AS course_code,
    course.name AS course_name,
    course.credits AS course_credits,
    lecturer_user.name AS lecturer_name,
    COALESCE(
        (
            SELECT json_agg(
                json_build_object(
                    'id', history.id,
                    'from_status', history.from_status,
                    'to_status', history.to_status,
                    'note', history.note,
                    'created_at', history.created_at,
                    'actor_id', history.created_by,
                    'actor_name', actor.name
                )
                ORDER BY history.created_at
            )
            FROM grade_status_history history
            INNER JOIN users actor ON actor.id = history.created_by
            WHERE history.grade_id = g.id
        ),
        '[]'::json
    ) AS status_history
FROM grades g
INNER JOIN enrollments e ON e.id = g.enrollment_id
INNER JOIN student_profiles student ON student.id = e.student_id
INNER JOIN users student_user ON student_user.id = student.user_id
INNER JOIN classes c ON c.id = e.class_id
INNER JOIN academic_terms term ON term.id = c.term_id
INNER JOIN courses course ON course.id = c.course_id
INNER JOIN lecturer_profiles lecturer ON lecturer.id = c.lecturer_id
INNER JOIN users lecturer_user ON lecturer_user.id = lecturer.user_id
SQL;
    }

    private function normalizeAll(array $grades): array
    {
        return array_map(fn (array $grade): array => $this->normalize($grade), $grades);
    }

    private function normalize(array $grade): array
    {
        foreach (['daily_score', 'attendance_score', 'midterm_score', 'final_exam_score', 'final_score'] as $field) {
            $grade[$field] = $grade[$field] === null ? null : (float) $grade[$field];
        }
        $grade['course_credits'] = (int) $grade['course_credits'];
        $history = json_decode($grade['status_history'], true);
        $grade['status_history'] = is_array($history) ? $history : [];

        return $grade;
    }
}
