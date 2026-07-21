<?php

declare(strict_types=1);

final class AuthController
{
    public function __construct(private AuthService $authService)
    {
    }

    public function login(): never
    {
        Response::send($this->authService->login(Request::json()));
    }

    public function me(array $user): never
    {
        Response::send([
            'user' => $this->authService->publicUser($user),
        ]);
    }

    public function logout(array $user): never
    {
        $this->authService->logout($user);
        Response::send([
            'message' => 'Logout berhasil.',
        ]);
    }

    public function forgotPassword(): never
    {
        $this->authService->requestPasswordReset(Request::json());
        Response::send(['message' => 'Jika email terdaftar, tautan reset password telah dikirim.']);
    }

    public function resetPassword(): never
    {
        $this->authService->resetPassword(Request::json());
        Response::send(['message' => 'Password berhasil diatur ulang.']);
    }
}
