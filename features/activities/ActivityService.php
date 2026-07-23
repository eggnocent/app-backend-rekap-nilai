<?php

declare(strict_types=1);

final class ActivityService
{
    public function __construct(private ActivityRepository $repository)
    {
    }

    public function all(): array
    {
        $limit = Request::query('limit');
        if ($limit === null) {
            return $this->repository->all();
        }

        if (!ctype_digit($limit) || (int) $limit < 1 || (int) $limit > 200) {
            Response::error('Limit aktivitas tidak valid.', 422);
        }

        return $this->repository->all((int) $limit);
    }
}
