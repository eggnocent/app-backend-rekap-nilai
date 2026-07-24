<?php

declare(strict_types=1);

final class UserService
{
    public function __construct(
        private PDO $connection,
        private UserRepository $repository,
        private ActivityRepository $activityRepository
    ) {
    }

    public function students(): array
    {
        $status = Request::query('status');
        if ($status !== null && !in_array($status, ['active', 'leave', 'inactive'], true)) {
            Response::error('Filter status mahasiswa tidak valid.', 422);
        }

        return $this->repository->students(
            Request::query('search'),
            $status,
            Request::query('major'),
            Pagination::fromRequest(),
        );
    }

    public function lecturers(): array
    {
        return $this->repository->lecturers(
            Request::query('search'),
            Request::query('major'),
            Pagination::fromRequest(),
        );
    }

    public function student(string $id): array
    {
        $student = $this->repository->student($id);

        if ($student === null) {
            Response::error('Mahasiswa tidak ditemukan.', 404);
        }

        // Rekap kehadiran disertakan di sini agar halaman detail cukup
        // satu permintaan untuk identitas + ringkasannya.
        $student['attendance'] = $this->repository->studentAttendanceSummary($id);

        return $student;
    }

    public function lecturer(string $id): array
    {
        $lecturer = $this->repository->lecturer($id);

        if ($lecturer === null) {
            Response::error('Dosen tidak ditemukan.', 404);
        }

        return $lecturer;
    }

    public function createStudent(array $payload, array $user): array
    {
        $account = $this->account($payload, true);
        $profile = $this->studentProfile($payload);
        $this->ensureStudentUnique($account['email'], $profile['nim']);

        $this->connection->beginTransaction();
        try {
            $userId = $this->repository->createUser($account, 'student', $profile['nim'], $user['id']);
            $profileId = $this->repository->createStudent($profile, $userId, $user['id']);
            $created = $this->repository->student($profileId);
            $this->activityRepository->create($user['id'], 80, 'create_student', 'Created student ' . $created['nim'] . '.', $user['role'], $user['email']);
            $this->connection->commit();

            return $created;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function updateStudent(string $id, array $payload, array $user): array
    {
        $existing = $this->requiredStudent($id);
        $account = $this->account(array_merge($existing, $payload), false, $payload);
        $profile = $this->studentProfile(array_merge($existing, $payload));
        $this->ensureStudentUnique($account['email'], $profile['nim'], $existing['user_id'], $id);

        $this->connection->beginTransaction();
        try {
            $this->repository->updateUser($existing['user_id'], $account, $profile['nim'], $user['id']);
            $this->repository->updateStudent($id, $profile, $user['id']);
            $updated = $this->repository->student($id);
            $this->activityRepository->create($user['id'], $this->accountActivityType($existing, $account), $this->accountActivity($existing, $account, 'student'), 'Updated student ' . $updated['nim'] . '.', $user['role'], $user['email']);
            $this->connection->commit();

            return $updated;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function deactivateStudent(string $id, array $user): array
    {
        $student = $this->requiredStudent($id);
        if ($student['status'] === 'inactive') {
            Response::error('Mahasiswa sudah nonaktif.', 422);
        }

        $this->connection->beginTransaction();
        try {
            $this->repository->deactivateStudent($id, $user['id']);
            $updated = $this->requiredStudent($id);
            $this->activityRepository->create($user['id'], 82, 'deactivate_student', 'Deactivated student ' . $updated['nim'] . '.', $user['role'], $user['email']);
            $this->connection->commit();
            return $updated;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function createLecturer(array $payload, array $user): array
    {
        $account = $this->account($payload, true);
        $profile = $this->lecturerProfile($payload);
        $this->ensureLecturerUnique($account['email'], $profile['nidn']);

        $this->connection->beginTransaction();
        try {
            $userId = $this->repository->createUser($account, 'lecturer', $profile['nidn'], $user['id']);
            $profileId = $this->repository->createLecturer($profile, $userId, $user['id']);
            $created = $this->repository->lecturer($profileId);
            $this->activityRepository->create($user['id'], 83, 'create_lecturer', 'Created lecturer ' . $created['nidn'] . '.', $user['role'], $user['email']);
            $this->connection->commit();

            return $created;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function updateLecturer(string $id, array $payload, array $user): array
    {
        $existing = $this->requiredLecturer($id);
        $account = $this->account(array_merge($existing, $payload), false, $payload);
        $profile = $this->lecturerProfile(array_merge($existing, $payload));
        $this->ensureLecturerUnique($account['email'], $profile['nidn'], $existing['user_id'], $id);

        $this->connection->beginTransaction();
        try {
            $this->repository->updateUser($existing['user_id'], $account, $profile['nidn'], $user['id']);
            $this->repository->updateLecturer($id, $profile, $user['id']);
            $updated = $this->repository->lecturer($id);
            $this->activityRepository->create($user['id'], $this->accountActivityType($existing, $account), $this->accountActivity($existing, $account, 'lecturer'), 'Updated lecturer ' . $updated['nidn'] . '.', $user['role'], $user['email']);
            $this->connection->commit();

            return $updated;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function profile(array $user): array
    {
        $profile = $user['role'] === 'student'
            ? $this->requiredStudentProfile($user['student_profile_id'] ?? null)
            : $this->requiredLecturerProfile($user['lecturer_profile_id'] ?? null);

        return $this->withAvatarUrl($profile);
    }

    public function updateProfile(array $payload, array $user): array
    {
        $existing = $this->profile($user);
        $name = $this->requiredString(array_merge($existing, $payload), 'name');
        $phone = $this->optionalString(array_merge($existing, $payload), 'phone');
        $this->repository->updateOwnProfile($user['id'], $name, $phone);
        $this->activityRepository->create($user['id'], 86, 'update_own_profile', 'Updated own profile.', $user['role'], $user['email']);

        return $this->profile($user);
    }

    public function uploadAvatar(array $file, array $user): array
    {
        $this->profile($user);
        $storage = new SupabaseStorage();
        $path = $storage->uploadAvatar($file, $user['id']);
        $previousPath = is_string($user['avatar_path'] ?? null) ? $user['avatar_path'] : null;

        $this->connection->beginTransaction();
        try {
            $this->repository->updateAvatarPath($user['id'], $path);
            $this->activityRepository->create($user['id'], 86, 'upload_avatar', 'Updated profile avatar.', $user['role'], $user['email']);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            try {
                $storage->delete($path);
            } catch (Throwable) {
            }
            throw $exception;
        }

        if ($previousPath !== null && $previousPath !== $path) {
            try {
                $storage->delete($previousPath);
            } catch (Throwable) {
            }
        }

        return ['avatar_path' => $path, 'avatar_url' => $storage->publicUrl($path)];
    }

    public function deleteAvatar(array $user): array
    {
        $this->profile($user);
        $path = is_string($user['avatar_path'] ?? null) ? $user['avatar_path'] : null;
        if ($path === null) {
            return ['avatar_path' => null, 'avatar_url' => null];
        }

        $this->connection->beginTransaction();
        try {
            $this->repository->updateAvatarPath($user['id'], null);
            $this->activityRepository->create($user['id'], 86, 'delete_avatar', 'Removed profile avatar.', $user['role'], $user['email']);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }

        try {
            (new SupabaseStorage())->delete($path);
        } catch (Throwable) {
        }

        return ['avatar_path' => null, 'avatar_url' => null];
    }

    private function requiredStudent(string $id): array
    {
        $student = $this->repository->student($id);
        if ($student === null) {
            Response::error('Mahasiswa tidak ditemukan.', 404);
        }

        return $student;
    }

    private function withAvatarUrl(array $profile): array
    {
        $profile['avatar_url'] = (new SupabaseStorage())->publicUrl(is_string($profile['avatar_path'] ?? null) ? $profile['avatar_path'] : null);

        return $profile;
    }

    private function requiredLecturer(string $id): array
    {
        $lecturer = $this->repository->lecturer($id);
        if ($lecturer === null) {
            Response::error('Dosen tidak ditemukan.', 404);
        }

        return $lecturer;
    }

    private function requiredStudentProfile(mixed $id): array
    {
        if (!is_string($id) || $id === '') {
            Response::error('Profil mahasiswa tidak ditemukan.', 403);
        }

        return $this->requiredStudent($id);
    }

    private function requiredLecturerProfile(mixed $id): array
    {
        if (!is_string($id) || $id === '') {
            Response::error('Profil dosen tidak ditemukan.', 403);
        }

        return $this->requiredLecturer($id);
    }

    private function account(array $payload, bool $passwordRequired, array $rawPayload = []): array
    {
        $email = strtolower($this->requiredString($payload, 'email'));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Response::error('Email tidak valid.', 422);
        }

        $passwordHash = null;
        $password = $passwordRequired ? ($payload['password'] ?? null) : ($rawPayload['password'] ?? null);
        if ($passwordRequired || $password !== null) {
            if (!is_string($password) || strlen($password) < 8) {
                Response::error('Password minimal 8 karakter.', 422);
            }
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        }

        $isActive = array_key_exists('is_active', $payload)
            ? filter_var($payload['is_active'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
            : true;
        if ($isActive === null) {
            Response::error('Status akun tidak valid.', 422);
        }

        return [
            'name' => $this->requiredString($payload, 'name'),
            'email' => $email,
            'phone' => $this->optionalString($payload, 'phone'),
            'is_active' => $isActive,
            'password_hash' => $passwordHash,
        ];
    }

    private function studentProfile(array $payload): array
    {
        $entryYear = $this->integer($payload, 'entry_year', 1900, 3000);
        $semester = $this->integer($payload, 'current_semester', 1, 20);
        $credits = $this->integer($payload, 'total_credits_target', 1, 300);
        $status = $this->requiredString($payload, 'status');
        if (!in_array($status, ['active', 'leave', 'inactive'], true)) {
            Response::error('Status mahasiswa tidak valid.', 422);
        }

        return [
            'nim' => $this->requiredString($payload, 'nim'),
            'faculty' => $this->requiredString($payload, 'faculty'),
            'major' => $this->requiredString($payload, 'major'),
            'entry_year' => $entryYear,
            'current_semester' => $semester,
            'total_credits_target' => $credits,
            'status' => $status,
        ];
    }

    private function lecturerProfile(array $payload): array
    {
        return [
            'nidn' => $this->requiredString($payload, 'nidn'),
            'faculty' => $this->requiredString($payload, 'faculty'),
            'major' => $this->requiredString($payload, 'major'),
        ];
    }

    private function ensureStudentUnique(string $email, string $nim, ?string $userId = null, ?string $profileId = null): void
    {
        if ($this->repository->emailExists($email, $userId)) {
            Response::error('Email sudah digunakan.', 422);
        }
        if ($this->repository->nimExists($nim, $profileId)) {
            Response::error('NIM sudah digunakan.', 422);
        }
    }

    private function ensureLecturerUnique(string $email, string $nidn, ?string $userId = null, ?string $profileId = null): void
    {
        if ($this->repository->emailExists($email, $userId)) {
            Response::error('Email sudah digunakan.', 422);
        }
        if ($this->repository->nidnExists($nidn, $profileId)) {
            Response::error('NIDN sudah digunakan.', 422);
        }
    }

    private function accountActivityType(array $existing, array $account): int
    {
        if ($this->boolean($existing['is_active']) !== $account['is_active']) {
            return 82;
        }

        return $account['password_hash'] === null ? 81 : 84;
    }

    private function accountActivity(array $existing, array $account, string $type): string
    {
        if ($this->boolean($existing['is_active']) !== $account['is_active']) {
            return $account['is_active'] ? 'activate_' . $type : 'deactivate_' . $type;
        }

        return $account['password_hash'] === null ? 'update_' . $type : 'reset_' . $type . '_password';
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

    private function integer(array $payload, string $key, int $minimum, int $maximum): int
    {
        $value = filter_var($payload[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < $minimum || $value > $maximum) {
            Response::error('Field ' . $key . ' tidak valid.', 422);
        }

        return $value;
    }

    private function boolean(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true'], true);
    }
}
