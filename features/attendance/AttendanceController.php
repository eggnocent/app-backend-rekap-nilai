<?php

declare(strict_types=1);

final class AttendanceController
{
    public function __construct(private AttendanceService $service)
    {
    }

    public function classAttendance(string $classId, array $user): never
    {
        Response::send(['data' => $this->service->classAttendance($classId, $user)]);
    }

    public function createMeeting(string $classId, array $user): never
    {
        Response::send(['data' => $this->service->createMeeting($classId, Request::json(), $user)], 201);
    }

    public function setRecord(string $meetingId, string $enrollmentId, array $user): never
    {
        Response::send(['data' => $this->service->setRecord($meetingId, $enrollmentId, Request::json(), $user)]);
    }

    public function index(): never
    {
        Response::send(['data' => $this->service->all()]);
    }

    public function mine(array $user): never
    {
        Response::send(['data' => $this->service->mine($user)]);
    }
}
