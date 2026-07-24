<?php

declare(strict_types=1);

final class EnrollmentController
{
    public function __construct(private EnrollmentService $service)
    {
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

    public function cancel(string $id, array $user): never
    {
        Response::send([
            'data' => $this->service->cancel($id, $user),
        ]);
    }

    public function mine(array $user): never
    {
        Response::send([
            'data' => $this->service->mine($user),
        ]);
    }

    public function mySchedule(array $user): never
    {
        Response::send([
            'data' => $this->service->mySchedule($user),
        ]);
    }
}
