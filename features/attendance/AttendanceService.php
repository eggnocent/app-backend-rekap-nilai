<?php

declare(strict_types=1);

final class AttendanceService
{
    public function __construct(
        private PDO $connection,
        private AttendanceRepository $repository,
        private EnrollmentRepository $enrollmentRepository,
        private ActivityRepository $activityRepository
    ) {
    }

    public function classAttendance(string $classId, array $user): array
    {
        $class = $this->requiredClass($classId);
        $this->authorizeClass($class, $user, true);

        return [
            'class' => $class,
            'meetings' => $this->repository->meetingsForClass($classId),
        ];
    }

    public function createMeeting(string $classId, array $payload, array $user): array
    {
        $class = $this->requiredClass($classId);
        $this->authorizeClass($class, $user, false);
        $meetingDate = $this->date($payload['meeting_date'] ?? null);
        $topic = $this->topic($payload['topic'] ?? null);

        if ($class['status'] !== 'Aktif' || $meetingDate < $class['start_date'] || $meetingDate > $class['end_date']) {
            Response::error('Pertemuan harus berada pada kelas aktif dan masa semester.', 422);
        }

        if ($this->repository->meetingExists($classId, $meetingDate)) {
            Response::error('Pertemuan pada tanggal tersebut sudah ada.', 422);
        }

        $this->connection->beginTransaction();

        try {
            $meetingId = $this->repository->createMeeting($classId, $meetingDate, $topic, $user['id']);
            $meeting = $this->repository->findMeeting($meetingId);
            $this->activityRepository->create($user['id'], 60, 'create_attendance_meeting', 'Created attendance meeting for class ' . $class['class_code'] . '.', $user['role'], $user['email']);
            $this->connection->commit();

            return $meeting;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function setRecord(string $meetingId, string $enrollmentId, array $payload, array $user): array
    {
        $meeting = $this->requiredMeeting($meetingId);
        $this->authorizeClass($meeting, $user, false);
        $status = $payload['status'] ?? null;

        if (!is_string($status) || !in_array($status, ['Hadir', 'Terlambat', 'Izin', 'Alpha'], true)) {
            Response::error('Status presensi tidak valid.', 422);
        }

        $enrollment = $this->repository->enrollmentForMeeting($meetingId, $enrollmentId);

        if ($enrollment === null || $enrollment['class_id'] !== $enrollment['meeting_class_id'] || $enrollment['enrollment_status'] !== 'Terdaftar') {
            Response::error('Enrollment tidak aktif pada kelas pertemuan.', 422);
        }

        $this->connection->beginTransaction();

        try {
            $this->repository->upsertRecord($meetingId, $enrollmentId, $status, $user['id']);
            $roster = $this->repository->roster($meetingId);
            $record = array_values(array_filter($roster, fn (array $item): bool => $item['enrollment_id'] === $enrollmentId))[0] ?? null;
            $this->activityRepository->create($user['id'], 61, 'set_attendance_record', 'Updated attendance record for class ' . $meeting['class_code'] . '.', $user['role'], $user['email']);
            $this->connection->commit();

            return $record;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function all(): array
    {
        return $this->repository->all(Request::query('term_id'), Request::query('class_id'), Request::query('meeting_id'));
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

        return $this->repository->mine($studentId, $term['id']);
    }

    private function requiredClass(string $id): array
    {
        $class = $this->repository->findClass($id);
        if ($class === null) {
            Response::error('Kelas tidak ditemukan.', 404);
        }
        return $class;
    }

    private function requiredMeeting(string $id): array
    {
        $meeting = $this->repository->findMeeting($id);
        if ($meeting === null) {
            Response::error('Pertemuan tidak ditemukan.', 404);
        }
        return $meeting;
    }

    private function authorizeClass(array $class, array $user, bool $adminAllowed): void
    {
        if ($user['role'] === 'admin' && $adminAllowed) {
            return;
        }
        if ($user['role'] !== 'lecturer' || $class['lecturer_id'] !== $user['lecturer_profile_id']) {
            Response::error('Anda tidak memiliki akses untuk presensi kelas ini.', 403);
        }
    }

    private function date(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || strtotime($value) === false) {
            Response::error('Tanggal pertemuan tidak valid.', 422);
        }
        return $value;
    }

    private function topic(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            Response::error('Topik pertemuan wajib diisi.', 422);
        }
        return trim($value);
    }
}
