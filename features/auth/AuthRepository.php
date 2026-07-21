<?php

declare(strict_types=1);

final class AuthRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function findUserByEmail(string $email): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT
                u.id,
                u.name,
                u.email,
                u.password_hash,
                u.role,
                u.identifier,
                u.phone,
                u.avatar_path,
                u.is_active,
                sp.id AS student_profile_id,
                sp.nim,
                lp.id AS lecturer_profile_id,
                lp.nidn
             FROM users u
             LEFT JOIN student_profiles sp ON sp.user_id = u.id
             LEFT JOIN lecturer_profiles lp ON lp.user_id = u.id
             WHERE u.email = :email
               AND u.is_active IS TRUE
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    public function findActiveUserForReset(string $email): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, name, email, role
             FROM users
             WHERE LOWER(email) = LOWER(:email)
               AND is_active IS TRUE
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    public function findAuthenticatedUser(string $tokenHash): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT
                s.id AS session_id,
                s.expires_at,
                u.id,
                u.name,
                u.email,
                u.role,
                u.identifier,
                u.phone,
                u.avatar_path,
                sp.id AS student_profile_id,
                sp.nim,
                lp.id AS lecturer_profile_id,
                lp.nidn
             FROM auth_sessions s
             INNER JOIN users u ON u.id = s.user_id
             LEFT JOIN student_profiles sp ON sp.user_id = u.id
             LEFT JOIN lecturer_profiles lp ON lp.user_id = u.id
             WHERE s.token_hash = :token_hash
               AND s.revoked_at IS NULL
               AND s.expires_at > NOW()
               AND u.is_active IS TRUE
             LIMIT 1'
        );
        $statement->execute(['token_hash' => $tokenHash]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    public function createSession(string $userId, string $tokenHash, string $expiresAt, ?string $ipAddress, ?string $userAgent): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO auth_sessions (user_id, token_hash, expires_at, last_used_at, ip_address, user_agent, created_by)
             VALUES (:user_id, :token_hash, :expires_at, NOW(), :ip_address, :user_agent, :created_by)'
        );
        $statement->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_by' => $userId,
        ]);
    }

    public function updateLastUsed(string $sessionId, string $userId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE auth_sessions
             SET last_used_at = NOW(), updated_at = NOW(), updated_by = :updated_by
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $sessionId,
            'updated_by' => $userId,
        ]);
    }

    public function revokeSession(string $sessionId, string $userId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE auth_sessions
             SET revoked_at = NOW(), updated_at = NOW(), updated_by = :updated_by
             WHERE id = :id AND revoked_at IS NULL'
        );
        $statement->execute([
            'id' => $sessionId,
            'updated_by' => $userId,
        ]);
    }

    public function updateLastLogin(string $userId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE users
             SET last_login_at = NOW(), updated_at = NOW(), updated_by = :updated_by
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function invalidateUnusedPasswordResetTokens(string $userId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE password_reset_tokens
             SET used_at = NOW(), updated_at = NOW(), updated_by = :updated_by
             WHERE user_id = :user_id
               AND used_at IS NULL'
        );
        $statement->execute(['user_id' => $userId, 'updated_by' => $userId]);
    }

    public function createPasswordResetToken(string $userId, string $tokenHash, string $expiresAt): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_by)
             VALUES (:user_id, :token_hash, :expires_at, :created_by)'
        );
        $statement->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'created_by' => $userId,
        ]);
    }

    public function findValidPasswordResetToken(string $tokenHash): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, user_id
             FROM password_reset_tokens
             WHERE token_hash = :token_hash
               AND used_at IS NULL
               AND expires_at > NOW()
             LIMIT 1'
        );
        $statement->execute(['token_hash' => $tokenHash]);
        $token = $statement->fetch();

        return is_array($token) ? $token : null;
    }

    public function resetPassword(string $userId, string $passwordHash): void
    {
        $statement = $this->connection->prepare(
            'UPDATE users
             SET password_hash = :password_hash, updated_at = NOW(), updated_by = :updated_by
             WHERE id = :id'
        );
        $statement->execute(['id' => $userId, 'password_hash' => $passwordHash, 'updated_by' => $userId]);
    }

    public function consumePasswordResetToken(string $tokenId, string $userId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE password_reset_tokens
             SET used_at = NOW(), updated_at = NOW(), updated_by = :updated_by
             WHERE id = :id'
        );
        $statement->execute(['id' => $tokenId, 'updated_by' => $userId]);
    }

    public function revokeUserSessions(string $userId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE auth_sessions
             SET revoked_at = NOW(), updated_at = NOW(), updated_by = :updated_by
             WHERE user_id = :user_id
               AND revoked_at IS NULL'
        );
        $statement->execute(['user_id' => $userId, 'updated_by' => $userId]);
    }
}
