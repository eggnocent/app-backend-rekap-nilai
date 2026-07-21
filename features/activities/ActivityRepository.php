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
}
