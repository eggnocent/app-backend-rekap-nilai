<?php

declare(strict_types=1);

final class ActivityService
{
    public function __construct(private ActivityRepository $repository)
    {
    }

    public function all(): array
    {
        return $this->repository->all(Pagination::fromRequest());
    }
}
