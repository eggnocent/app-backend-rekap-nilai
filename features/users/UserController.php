<?php

declare(strict_types=1);

final class UserController
{
    public function __construct(private UserService $service)
    {
    }

    public function students(): never
    {
        Response::send($this->service->students());
    }

    public function createStudent(array $user): never
    {
        Response::send(['data' => $this->service->createStudent(Request::json(), $user)], 201);
    }

    public function updateStudent(string $id, array $user): never
    {
        Response::send(['data' => $this->service->updateStudent($id, Request::json(), $user)]);
    }

    public function deactivateStudent(string $id, array $user): never
    {
        Response::send(['data' => $this->service->deactivateStudent($id, $user)]);
    }

    public function lecturers(): never
    {
        Response::send($this->service->lecturers());
    }

    public function createLecturer(array $user): never
    {
        Response::send(['data' => $this->service->createLecturer(Request::json(), $user)], 201);
    }

    public function updateLecturer(string $id, array $user): never
    {
        Response::send(['data' => $this->service->updateLecturer($id, Request::json(), $user)]);
    }

    public function profile(array $user): never
    {
        Response::send(['data' => $this->service->profile($user)]);
    }

    public function updateProfile(array $user): never
    {
        Response::send(['data' => $this->service->updateProfile(Request::json(), $user)]);
    }

    public function uploadAvatar(array $user): never
    {
        Response::send(['data' => $this->service->uploadAvatar(Request::file('avatar'), $user)]);
    }

    public function deleteAvatar(array $user): never
    {
        Response::send(['data' => $this->service->deleteAvatar($user)]);
    }
}
