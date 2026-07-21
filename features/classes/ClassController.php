<?php

declare(strict_types=1);

final class ClassController
{
    public function __construct(private ClassService $service)
    {
    }

    public function index(array $user): never
    {
        Response::send([
            'data' => $this->service->all($user),
        ]);
    }

    public function show(string $id, array $user): never
    {
        Response::send([
            'data' => $this->service->find($id, $user),
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

    public function close(string $id, array $user): never
    {
        Response::send([
            'data' => $this->service->close($id, $user),
        ]);
    }
}
