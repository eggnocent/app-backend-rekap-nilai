<?php

declare(strict_types=1);

final class AuthMiddleware
{
    public function __construct(private AuthService $authService)
    {
    }

    public function authenticate(array $roles = []): array
    {
        $token = Request::bearerToken();

        if ($token === null) {
            Response::error('Bearer token wajib disertakan.', 401);
        }

        $user = $this->authService->authenticatedUser($token);

        if ($roles !== [] && !in_array($user['role'], $roles, true)) {
            Response::error('Anda tidak memiliki akses untuk endpoint ini.', 403);
        }

        return $user;
    }
}
