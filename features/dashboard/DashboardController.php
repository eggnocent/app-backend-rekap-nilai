<?php

declare(strict_types=1);

final class DashboardController
{
    public function __construct(private DashboardService $service)
    {
    }

    public function show(array $user): never
    {
        Response::send(['data' => $this->service->show($user)]);
    }
}
