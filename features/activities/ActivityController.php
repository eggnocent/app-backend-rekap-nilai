<?php

declare(strict_types=1);

final class ActivityController
{
    public function __construct(private ActivityService $service)
    {
    }

    public function index(): never
    {
        Response::send(['data' => $this->service->all()]);
    }
}
