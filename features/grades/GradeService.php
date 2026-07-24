<?php

declare(strict_types=1);

final class GradeService
{
    public function __construct(
        private PDO $connection,
        private GradeRepository $repository,
        private EnrollmentRepository $enrollmentRepository,
        private ActivityRepository $activityRepository
    ) {
    }

    public function roster(string $classId, array $user): array
    {
        $class = $this->classService()->find($classId, $user);

        return [
            'class' => $class,
            'students' => $this->repository->roster($classId),
        ];
    }

    public function saveDraft(string $enrollmentId, array $payload, array $user): array
    {
        $enrollment = $this->repository->findEnrollment($enrollmentId);
        $this->authorizeLecturer($enrollment, $user);

        if ($enrollment['enrollment_status'] !== 'Terdaftar') {
            Response::error('Enrollment tidak aktif.', 422);
        }

        $scores = $this->validatedScores($payload);
        $existing = $this->repository->findByEnrollment($enrollmentId);

        if ($existing !== null && !in_array($existing['status'], ['draft', 'returned'], true)) {
            Response::error('Nilai yang sudah dikirim tidak dapat diubah.', 422);
        }

        $this->connection->beginTransaction();

        try {
            if ($existing === null) {
                $gradeId = $this->repository->create($enrollmentId, $scores, $user['id']);
                $this->repository->addHistory($gradeId, null, 'draft', null, $user['id']);
            } else {
                $gradeId = $existing['id'];
                $this->repository->updateScores($gradeId, $scores, $user['id']);
            }

            $grade = $this->repository->find($gradeId);
            $this->activityRepository->create($user['id'], 50, 'save_grade_draft', 'Saved grade draft for ' . $enrollment['student_name'] . ' in class ' . $enrollment['class_code'] . '.', $user['role'], $user['email']);
            $this->connection->commit();

            return $grade;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    public function submit(string $id, array $user): array
    {
        $grade = $this->repository->find($id);
        $this->authorizeLecturer($grade, $user);

        if (!in_array($grade['status'], ['draft', 'returned'], true)) {
            Response::error('Status nilai tidak dapat dikirim.', 422);
        }

        return $this->transition($grade, 'submitted', null, $user, 51, 'submit_grade', 'Submitted grade for verification.');
    }

    public function all(): array
    {
        $status = Request::query('status');

        if ($status !== null && !in_array($status, ['draft', 'submitted', 'returned', 'verified', 'published'], true)) {
            Response::error('Filter status tidak valid.', 422);
        }

        return $this->repository->all($status, Request::query('class_id'), Request::query('term_id'), Pagination::fromRequest());
    }

    public function verify(string $id, array $user): array
    {
        $grade = $this->requiredGrade($id);

        if ($grade['status'] !== 'submitted') {
            Response::error('Hanya nilai submitted yang dapat diverifikasi.', 422);
        }

        return $this->transition($grade, 'verified', null, $user, 52, 'verify_grade', 'Verified grade.');
    }

    public function returnGrade(string $id, array $payload, array $user): array
    {
        $grade = $this->requiredGrade($id);
        $note = $payload['note'] ?? null;

        if ($grade['status'] !== 'submitted') {
            Response::error('Hanya nilai submitted yang dapat dikembalikan.', 422);
        }

        if (!is_string($note) || trim($note) === '') {
            Response::error('Catatan pengembalian wajib diisi.', 422);
        }

        return $this->transition($grade, 'returned', trim($note), $user, 53, 'return_grade', 'Returned grade for revision.');
    }

    public function publish(string $id, array $user): array
    {
        $grade = $this->requiredGrade($id);

        if ($grade['status'] !== 'verified') {
            Response::error('Hanya nilai verified yang dapat dipublikasikan.', 422);
        }

        return $this->transition($grade, 'published', null, $user, 54, 'publish_grade', 'Published grade.');
    }

    public function mine(array $user): array
    {
        $studentId = $user['student_profile_id'] ?? null;

        if (!is_string($studentId) || $studentId === '') {
            Response::error('Profil mahasiswa tidak ditemukan.', 403);
        }

        $term = $this->enrollmentRepository->activeTerm();

        if ($term === null) {
            Response::error('Semester aktif tidak ditemukan.', 404);
        }

        return array_map(function (array $grade): array {
            if ($grade['status'] === 'published') {
                return $grade;
            }

            return [
                'id' => $grade['id'],
                'enrollment_id' => $grade['enrollment_id'],
                'class_id' => $grade['class_id'],
                'class_code' => $grade['class_code'],
                'term_id' => $grade['term_id'],
                'term_name' => $grade['term_name'],
                'course_code' => $grade['course_code'],
                'course_name' => $grade['course_name'],
                'status' => $grade['status'],
            ];
        }, $this->repository->mine($studentId, $term['id']));
    }

    public function transcript(array $user): array
    {
        $studentId = $user['student_profile_id'] ?? null;

        if (!is_string($studentId) || $studentId === '') {
            Response::error('Profil mahasiswa tidak ditemukan.', 403);
        }

        return $this->repository->transcript($studentId);
    }

    private function transition(array $grade, string $status, ?string $note, array $user, int $activityType, string $activity, string $message): array
    {
        $this->connection->beginTransaction();

        try {
            $this->repository->transition($grade['id'], $status, $user['id']);
            $this->repository->addHistory($grade['id'], $grade['status'], $status, $note, $user['id']);
            $updated = $this->repository->find($grade['id']);
            $this->activityRepository->create($user['id'], $activityType, $activity, $message . ' ' . $grade['class_code'], $user['role'], $user['email']);
            $this->connection->commit();

            return $updated;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    private function requiredGrade(string $id): array
    {
        $grade = $this->repository->find($id);

        if ($grade === null) {
            Response::error('Nilai tidak ditemukan.', 404);
        }

        return $grade;
    }

    private function authorizeLecturer(?array $record, array $user): void
    {
        if ($record === null) {
            Response::error('Data tidak ditemukan.', 404);
        }

        if ($record['lecturer_id'] !== $user['lecturer_profile_id']) {
            Response::error('Anda tidak memiliki akses untuk nilai kelas ini.', 403);
        }
    }

    private function validatedScores(array $payload): array
    {
        $assignment = $this->score($payload, 'assignment_score');
        $midterm = $this->score($payload, 'midterm_score');
        $finalExam = $this->score($payload, 'final_exam_score');
        $finalScore = round(($assignment * 0.3) + ($midterm * 0.3) + ($finalExam * 0.4), 2);

        return [
            'assignment_score' => $assignment,
            'midterm_score' => $midterm,
            'final_exam_score' => $finalExam,
            'final_score' => $finalScore,
            'letter_grade' => $this->letterGrade($finalScore),
        ];
    }

    private function score(array $payload, string $key): float
    {
        $value = $payload[$key] ?? null;

        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0 || (float) $value > 100) {
            Response::error('Field ' . $key . ' harus berupa angka 0 sampai 100.', 422);
        }

        return round((float) $value, 2);
    }

    private function letterGrade(float $score): string
    {
        return match (true) {
            $score >= 80 => 'A',
            $score >= 70 => 'B',
            $score >= 60 => 'C',
            $score >= 50 => 'D',
            default => 'E',
        };
    }

    private function classService(): ClassService
    {
        return new ClassService($this->connection, new ClassRepository($this->connection), $this->activityRepository);
    }
}
