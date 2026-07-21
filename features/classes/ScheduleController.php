<?php

declare(strict_types=1);

final class ScheduleController
{
    public function __construct(private ClassService $service)
    {
    }

    public function index(array $user): never
    {
        Response::send([
            'data' => $this->service->schedules($user),
        ]);
    }

    public function replace(string $classId, array $user): never
    {
        Response::send([
            'data' => $this->service->replaceSchedules($classId, Request::json(), $user),
        ]);
    }
}
