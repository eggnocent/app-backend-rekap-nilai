<?php

declare(strict_types=1);

final class DashboardService
{
    public function __construct(private DashboardRepository $repository)
    {
    }

    public function show(array $user): array
    {
        $term = $this->repository->activeTerm();

        if ($term === null) {
            return [
                'role' => $user['role'],
                'term' => null,
                'dashboard' => $this->emptyDashboard($user['role']),
            ];
        }

        $dashboard = match ($user['role']) {
            'admin' => $this->repository->admin($term['id']),
            'lecturer' => $this->lecturerDashboard($user, $term['id']),
            'student' => $this->studentDashboard($user, $term['id']),
            default => [],
        };

        return [
            'role' => $user['role'],
            'term' => $term,
            'dashboard' => $dashboard,
        ];
    }

    private function lecturerDashboard(array $user, string $termId): array
    {
        $lecturerId = $user['lecturer_profile_id'] ?? null;

        if (!is_string($lecturerId) || $lecturerId === '') {
            Response::error('Profil dosen tidak ditemukan.', 403);
        }

        return $this->repository->lecturer($termId, $lecturerId, (int) (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('N'));
    }

    private function studentDashboard(array $user, string $termId): array
    {
        $studentId = $user['student_profile_id'] ?? null;

        if (!is_string($studentId) || $studentId === '') {
            Response::error('Profil mahasiswa tidak ditemukan.', 403);
        }

        return $this->repository->student($termId, $studentId);
    }

    private function emptyDashboard(string $role): array
    {
        return match ($role) {
            'admin' => [
                'metrics' => ['students' => 0, 'active_courses' => 0, 'grade_records' => 0, 'active_classes' => 0],
                'grade_distribution' => [],
                'classes' => [],
                'activities' => [],
                'upcoming_events' => [],
            ],
            'lecturer' => [
                'metrics' => ['classes' => 0, 'students' => 0, 'grade_completion' => 0, 'average_final_score' => null],
                'classes' => [],
                'today_schedule' => [],
                'upcoming_events' => [],
            ],
            'student' => [
                'metrics' => ['active_classes' => 0, 'total_credits' => 0, 'published_grades' => 0],
                'attendance' => ['meetings' => 0, 'present' => 0, 'unrecorded' => 0],
                'classes' => [],
                'published_grades' => [],
                'upcoming_events' => [],
            ],
            default => [],
        };
    }
}
