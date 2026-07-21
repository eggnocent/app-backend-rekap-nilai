<?php

declare(strict_types=1);

final class CourseController
{
    public function __construct(private CourseService $service)
    {
    }

    public function index(array $user): never
    {
        Response::send([
            'data' => $this->service->all($user),
        ]);
    }

    public function create(array $user): never
    {
        Response::send([
            'data' => $this->service->create(Request::json(), $user),
        ], 201);
    }

    public function update(string $id, array $user): never
    {
        Response::send([
            'data' => $this->service->update($id, Request::json(), $user),
        ]);
    }
}
