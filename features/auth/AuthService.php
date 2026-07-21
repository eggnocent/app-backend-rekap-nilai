<?php

declare(strict_types=1);

final class AuthService
{
    public function __construct(
        private PDO $connection,
        private AuthRepository $authRepository,
        private ActivityRepository $activityRepository
    ) {
    }

    public function login(array $payload): array
    {
        $email = isset($payload['email']) && is_string($payload['email']) ? strtolower(trim($payload['email'])) : '';
        $password = isset($payload['password']) && is_string($payload['password']) ? $payload['password'] : '';
        $remember = isset($payload['remember']) && $payload['remember'] === true;

        if ($email === '' || $password === '') {
            Response::error('Email dan password wajib diisi.', 422);
        }

        $user = $this->authRepository->findUserByEmail($email);

        if ($user === null || !is_string($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            Response::error('Email atau password tidak valid.', 401);
        }

        $token = Token::generate();
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify($remember ? '+30 days' : '+8 hours')
            ->format('Y-m-d H:i:sP');
        $userId = $user['id'];

        $this->authRepository->createSession($userId, Token::hash($token), $expiresAt, Request::ipAddress(), Request::userAgent());
        $this->authRepository->updateLastLogin($userId);
        $this->activityRepository->create($userId, 1, 'login', 'User logged in.', $user['role'], $user['email']);

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt,
            'user' => $this->publicUser($user),
        ];
    }

    public function authenticatedUser(string $token): array
    {
        $user = $this->authRepository->findAuthenticatedUser(Token::hash($token));

        if ($user === null) {
            Response::error('Sesi autentikasi tidak valid atau telah berakhir.', 401);
        }

        $this->authRepository->updateLastUsed($user['session_id'], $user['id']);

        return $user;
    }

    public function logout(array $user): void
    {
        $this->authRepository->revokeSession($user['session_id'], $user['id']);
        $this->activityRepository->create($user['id'], 2, 'logout', 'User logged out.', $user['role'], $user['email']);
    }

    public function requestPasswordReset(array $payload): void
    {
        $email = isset($payload['email']) && is_string($payload['email']) ? strtolower(trim($payload['email'])) : '';
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Response::error('Email tidak valid.', 422);
        }

        $user = $this->authRepository->findActiveUserForReset($email);
        if ($user === null) {
            return;
        }

        $token = Token::generate();
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+30 minutes')->format('Y-m-d H:i:sP');
        $this->authRepository->invalidateUnusedPasswordResetTokens($user['id']);
        $this->authRepository->createPasswordResetToken($user['id'], Token::hash($token), $expiresAt);

        try {
            (new ResendMailer())->sendPasswordReset($user['email'], $user['name'], $token);
            $this->activityRepository->create($user['id'], 3, 'request_password_reset', 'Requested password reset.', $user['role'], $user['email']);
        } catch (Throwable) {
        }
    }

    public function resetPassword(array $payload): void
    {
        $token = isset($payload['token']) && is_string($payload['token']) ? trim($payload['token']) : '';
        $password = isset($payload['password']) && is_string($payload['password']) ? $payload['password'] : '';
        if ($token === '' || strlen($password) < 8) {
            Response::error('Token atau password tidak valid.', 422);
        }

        $resetToken = $this->authRepository->findValidPasswordResetToken(Token::hash($token));
        if ($resetToken === null) {
            Response::error('Token reset password tidak valid atau sudah kedaluwarsa.', 422);
        }

        $this->connection->beginTransaction();
        try {
            $this->authRepository->resetPassword($resetToken['user_id'], password_hash($password, PASSWORD_DEFAULT));
            $this->authRepository->consumePasswordResetToken($resetToken['id'], $resetToken['user_id']);
            $this->authRepository->revokeUserSessions($resetToken['user_id']);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function publicUser(array $user): array
    {
        return [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'identifier' => $user['identifier'],
            'phone' => $user['phone'],
            'avatar_path' => $user['avatar_path'],
            'student_profile_id' => $user['student_profile_id'],
            'nim' => $user['nim'],
            'lecturer_profile_id' => $user['lecturer_profile_id'],
            'nidn' => $user['nidn'],
        ];
    }
}
