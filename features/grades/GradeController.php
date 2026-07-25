<?php

declare(strict_types=1);

final class GradeController
{
    public function __construct(private GradeService $service)
    {
    }

    public function roster(string $classId, array $user): never
    {
        Response::send([
            'data' => $this->service->roster($classId, $user),
        ]);
    }

    public function meetingScores(string $meetingId, array $user): never
    {
        Response::send([
            'data' => $this->service->meetingScoreRoster($meetingId, $user),
        ]);
    }

    public function saveMeetingScores(string $meetingId, array $user): never
    {
        Response::send([
            'data' => $this->service->saveMeetingScores($meetingId, Request::json(), $user),
        ]);
    }

    public function saveDraft(string $enrollmentId, array $user): never
    {
        Response::send([
            'data' => $this->service->saveDraft($enrollmentId, Request::json(), $user),
        ]);
    }

    public function submit(string $id, array $user): never
    {
        Response::send([
            'data' => $this->service->submit($id, $user),
        ]);
    }

    public function index(): never
    {
        Response::send($this->service->all());
    }

    public function verify(string $id, array $user): never
    {
        Response::send([
            'data' => $this->service->verify($id, $user),
        ]);
    }

    public function returnGrade(string $id, array $user): never
    {
        Response::send([
            'data' => $this->service->returnGrade($id, Request::json(), $user),
        ]);
    }

    public function publish(string $id, array $user): never
    {
        Response::send([
            'data' => $this->service->publish($id, $user),
        ]);
    }

    public function mine(array $user): never
    {
        Response::send([
            'data' => $this->service->mine($user),
        ]);
    }

    public function transcript(array $user): never
    {
        Response::send([
            'data' => $this->service->transcript($user),
        ]);
    }
}
