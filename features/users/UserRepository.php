<?php

declare(strict_types=1);

final class UserRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function students(?string $search, ?string $status, ?string $major, Pagination $pagination): array
    {
        $conditions = [];
        $parameters = [];

        if ($search !== null) {
            $conditions[] = '(LOWER(u.name) LIKE :search OR LOWER(u.email) LIKE :search OR LOWER(sp.nim) LIKE :search)';
            $parameters['search'] = '%' . strtolower($search) . '%';
        }
        if ($status !== null) {
            $conditions[] = 'sp.status = :status';
            $parameters['status'] = $status;
        }
        if ($major !== null) {
            $conditions[] = 'sp.major = :major';
            $parameters['major'] = $major;
        }

        $base = $this->studentSelect();
        if ($conditions !== []) {
            $base .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $total = Pagination::total($this->connection, $base, $parameters);
        $statement = $this->connection->prepare($pagination->apply($base . ' ORDER BY sp.nim, u.name'));
        $statement->execute($parameters);

        return $pagination->envelope($this->normalizeStudents($statement->fetchAll()), $total);
    }

    public function lecturers(?string $search, ?string $major, Pagination $pagination): array
    {
        $conditions = [];
        $parameters = [];

        if ($search !== null) {
            $conditions[] = '(LOWER(u.name) LIKE :search OR LOWER(u.email) LIKE :search OR LOWER(lp.nidn) LIKE :search)';
            $parameters['search'] = '%' . strtolower($search) . '%';
        }
        if ($major !== null) {
            $conditions[] = 'lp.major = :major';
            $parameters['major'] = $major;
        }

        $base = $this->lecturerSelect();
        if ($conditions !== []) {
            $base .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $total = Pagination::total($this->connection, $base, $parameters);
        $statement = $this->connection->prepare($pagination->apply($base . ' ORDER BY lp.nidn, u.name'));
        $statement->execute($parameters);

        return $pagination->envelope(
            array_map(fn (array $lecturer): array => $this->normalizeLecturer($lecturer), $statement->fetchAll()),
            $total,
        );
    }

    public function student(string $id): ?array
    {
        $statement = $this->connection->prepare($this->studentSelect() . ' WHERE sp.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $student = $statement->fetch();

        return is_array($student) ? $this->normalizeStudent($student) : null;
    }

    public function lecturer(string $id): ?array
    {
        $statement = $this->connection->prepare($this->lecturerSelect() . ' WHERE lp.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $lecturer = $statement->fetch();

        return is_array($lecturer) ? $this->normalizeLecturer($lecturer) : null;
    }

    public function emailExists(string $email, ?string $exceptUserId = null): bool
    {
        $query = 'SELECT 1 FROM users WHERE LOWER(email) = LOWER(:email)';
        $parameters = ['email' => $email];
        if ($exceptUserId !== null) {
            $query .= ' AND id <> :except_user_id';
            $parameters['except_user_id'] = $exceptUserId;
        }
        $statement = $this->connection->prepare($query);
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    public function nimExists(string $nim, ?string $exceptProfileId = null): bool
    {
        return $this->profileIdentifierExists('student_profiles', 'nim', $nim, $exceptProfileId);
    }

    public function nidnExists(string $nidn, ?string $exceptProfileId = null): bool
    {
        return $this->profileIdentifierExists('lecturer_profiles', 'nidn', $nidn, $exceptProfileId);
    }

    public function createUser(array $account, string $role, string $identifier, string $actorId): string
    {
        $statement = $this->connection->prepare(
            'INSERT INTO users (name, email, password_hash, role, identifier, phone, is_active, created_by)
             VALUES (:name, :email, :password_hash, :role, :identifier, :phone, :is_active, :created_by)
             RETURNING id'
        );
        $this->executeWithBoolean($statement, [
            'name' => $account['name'],
            'email' => $account['email'],
            'password_hash' => $account['password_hash'],
            'role' => $role,
            'identifier' => $identifier,
            'phone' => $account['phone'],
            'is_active' => $account['is_active'],
            'created_by' => $actorId,
        ]);

        return (string) $statement->fetchColumn();
    }

    public function createStudent(array $profile, string $userId, string $actorId): string
    {
        $statement = $this->connection->prepare(
            'INSERT INTO student_profiles (user_id, nim, faculty, major, entry_year, current_semester, total_credits_target, status, created_by)
             VALUES (:user_id, :nim, :faculty, :major, :entry_year, :current_semester, :total_credits_target, :status, :created_by)
             RETURNING id'
        );
        $statement->execute($profile + ['user_id' => $userId, 'created_by' => $actorId]);

        return (string) $statement->fetchColumn();
    }

    public function createLecturer(array $profile, string $userId, string $actorId): string
    {
        $statement = $this->connection->prepare(
            'INSERT INTO lecturer_profiles (user_id, nidn, faculty, major, created_by)
             VALUES (:user_id, :nidn, :faculty, :major, :created_by)
             RETURNING id'
        );
        $statement->execute($profile + ['user_id' => $userId, 'created_by' => $actorId]);

        return (string) $statement->fetchColumn();
    }

    public function updateUser(string $userId, array $account, string $identifier, string $actorId): void
    {
        $query =
            'UPDATE users
             SET name = :name,
                 email = :email,
                 phone = :phone,
                 identifier = :identifier,
                 is_active = :is_active,
                 updated_at = NOW(),
                 updated_by = :updated_by';
        $parameters = [
            'name' => $account['name'],
            'email' => $account['email'],
            'phone' => $account['phone'],
            'identifier' => $identifier,
            'is_active' => $account['is_active'],
            'updated_by' => $actorId,
            'id' => $userId,
        ];
        if ($account['password_hash'] !== null) {
            $query .= ', password_hash = :password_hash';
            $parameters['password_hash'] = $account['password_hash'];
        }
        $query .= ' WHERE id = :id';
        $statement = $this->connection->prepare($query);
        $this->executeWithBoolean($statement, $parameters);
    }

    public function updateStudent(string $id, array $profile, string $actorId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE student_profiles
             SET nim = :nim,
                 faculty = :faculty,
                 major = :major,
                 entry_year = :entry_year,
                 current_semester = :current_semester,
                 total_credits_target = :total_credits_target,
                 status = :status,
                 updated_at = NOW(),
                 updated_by = :updated_by
             WHERE id = :id'
        );
        $statement->execute($profile + ['id' => $id, 'updated_by' => $actorId]);
    }

    public function deactivateStudent(string $id, string $actorId): void
    {
        $student = $this->student($id);
        if ($student === null) {
            return;
        }
        $statement = $this->connection->prepare(
            "UPDATE student_profiles SET status = 'inactive', updated_at = NOW(), updated_by = :updated_by WHERE id = :id"
        );
        $statement->execute(['id' => $id, 'updated_by' => $actorId]);
        $statement = $this->connection->prepare(
            'UPDATE users SET is_active = FALSE, updated_at = NOW(), updated_by = :updated_by WHERE id = :id'
        );
        $statement->execute(['id' => $student['user_id'], 'updated_by' => $actorId]);
    }

    public function updateLecturer(string $id, array $profile, string $actorId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE lecturer_profiles
             SET nidn = :nidn,
                 faculty = :faculty,
                 major = :major,
                 updated_at = NOW(),
                 updated_by = :updated_by
             WHERE id = :id'
        );
        $statement->execute($profile + ['id' => $id, 'updated_by' => $actorId]);
    }

    public function updateOwnProfile(string $userId, string $name, ?string $phone): void
    {
        $statement = $this->connection->prepare(
            'UPDATE users
             SET name = :name, phone = :phone, updated_at = NOW(), updated_by = :updated_by
             WHERE id = :id'
        );
        $statement->execute(['id' => $userId, 'name' => $name, 'phone' => $phone, 'updated_by' => $userId]);
    }

    public function updateAvatarPath(string $userId, ?string $path): void
    {
        $statement = $this->connection->prepare(
            'UPDATE users
             SET avatar_path = :avatar_path, updated_at = NOW(), updated_by = :updated_by
             WHERE id = :id'
        );
        $statement->execute(['id' => $userId, 'avatar_path' => $path, 'updated_by' => $userId]);
    }

    private function profileIdentifierExists(string $table, string $column, string $value, ?string $exceptProfileId): bool
    {
        $query = 'SELECT 1 FROM ' . $table . ' WHERE LOWER(' . $column . ') = LOWER(:value)';
        $parameters = ['value' => $value];
        if ($exceptProfileId !== null) {
            $query .= ' AND id <> :except_profile_id';
            $parameters['except_profile_id'] = $exceptProfileId;
        }
        $statement = $this->connection->prepare($query);
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    private function studentSelect(): string
    {
        return 'SELECT sp.id, sp.user_id, sp.nim, sp.faculty, sp.major, sp.entry_year, sp.current_semester, sp.total_credits_target, sp.status,
                       u.name, u.email, u.phone, u.avatar_path, u.is_active, u.last_login_at
                FROM student_profiles sp
                INNER JOIN users u ON u.id = sp.user_id';
    }

    private function lecturerSelect(): string
    {
        return 'SELECT lp.id, lp.user_id, lp.nidn, lp.faculty, lp.major,
                       u.name, u.email, u.phone, u.avatar_path, u.is_active, u.last_login_at
                FROM lecturer_profiles lp
                INNER JOIN users u ON u.id = lp.user_id';
    }

    private function normalizeStudents(array $students): array
    {
        return array_map(fn (array $student): array => $this->normalizeStudent($student), $students);
    }

    private function normalizeStudent(array $student): array
    {
        foreach (['entry_year', 'current_semester', 'total_credits_target'] as $field) {
            $student[$field] = $student[$field] === null ? null : (int) $student[$field];
        }
        $student['is_active'] = $this->boolean($student['is_active']);

        return $student;
    }

    private function normalizeLecturer(array $lecturer): array
    {
        $lecturer['is_active'] = $this->boolean($lecturer['is_active']);

        return $lecturer;
    }

    private function boolean(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true'], true);
    }

    private function executeWithBoolean(PDOStatement $statement, array $parameters): void
    {
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_bool($value) ? PDO::PARAM_BOOL : PDO::PARAM_STR);
        }
        $statement->execute();
    }
}
