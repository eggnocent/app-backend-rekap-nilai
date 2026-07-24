<?php

declare(strict_types=1);

final class ActivityRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function create(string $userId, int $activityType, string $activity, string $activityString, string $role, string $email): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO activity_log (user_id, activity_type, activity, activity_string, role, created_by, email)
             VALUES (:user_id, :activity_type, :activity, :activity_string, :role, :created_by, :email)'
        );
        $statement->execute([
            'user_id' => $userId,
            'activity_type' => $activityType,
            'activity' => $activity,
            'activity_string' => $activityString,
            'role' => $role,
            'created_by' => $userId,
            'email' => $email,
        ]);
    }

    public function all(Pagination $pagination): array
    {
        $base = 'SELECT id, user_id, activity_type, activity, activity_string, role, email, created_at FROM activity_log';

        $total = Pagination::total($this->connection, $base, []);
        $statement = $this->connection->prepare($pagination->apply($base . ' ORDER BY created_at DESC'));
        $statement->execute();

        $rows = array_map(function (array $activity): array {
            $activity['activity_type'] = (int) $activity['activity_type'];
            return $activity;
        }, $statement->fetchAll());

        return $pagination->envelope($rows, $total);
    }
}
