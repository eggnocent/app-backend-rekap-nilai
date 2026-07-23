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

    public function all(?int $limit = null): array
    {
        $query = 'SELECT id, user_id, activity_type, activity, activity_string, role, email, created_at FROM activity_log ORDER BY created_at DESC';
        if ($limit !== null) {
            $query .= ' LIMIT :limit';
        }
        $statement = $this->connection->prepare($query);
        if ($limit !== null) {
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $statement->execute();

        return array_map(function (array $activity): array {
            $activity['activity_type'] = (int) $activity['activity_type'];
            return $activity;
        }, $statement->fetchAll());
    }
}
