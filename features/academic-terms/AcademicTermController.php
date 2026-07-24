<?php

declare(strict_types=1);

final class AcademicTermController
{
    public function __construct(private AcademicTermService $service)
    {
    }

    public function active(): never
    {
        Response::send([
            'data' => $this->service->active(),
        ]);
    }

    public function index(): never
    {
        Response::send($this->service->all());
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
