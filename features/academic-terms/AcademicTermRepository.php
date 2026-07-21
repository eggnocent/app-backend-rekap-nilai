<?php

declare(strict_types=1);

final class AcademicTermRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function active(): ?array
    {
        $statement = $this->connection->query(
            'SELECT id, name, academic_year, semester, start_date, end_date, is_active
             FROM academic_terms
             WHERE is_active IS TRUE
             ORDER BY start_date DESC NULLS LAST
             LIMIT 1'
        );
        $term = $statement->fetch();

        return is_array($term) ? $term : null;
    }

    public function all(): array
    {
        $statement = $this->connection->query(
            'SELECT id, name, academic_year, semester, start_date, end_date, is_active
             FROM academic_terms
             ORDER BY start_date DESC NULLS LAST, created_at DESC'
        );

        return $statement->fetchAll();
    }

    public function find(string $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, name, academic_year, semester, start_date, end_date, is_active
             FROM academic_terms
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $term = $statement->fetch();

        return is_array($term) ? $term : null;
    }

    public function create(array $term, string $userId): array
    {
        $statement = $this->connection->prepare(
            'INSERT INTO academic_terms (name, academic_year, semester, start_date, end_date, is_active, created_by)
             VALUES (:name, :academic_year, :semester, :start_date, :end_date, :is_active, :created_by)
             RETURNING id, name, academic_year, semester, start_date, end_date, is_active'
        );
        $statement->execute([
            'name' => $term['name'],
            'academic_year' => $term['academic_year'],
            'semester' => $term['semester'],
            'start_date' => $term['start_date'],
            'end_date' => $term['end_date'],
            'is_active' => $term['is_active'],
            'created_by' => $userId,
        ]);

        return $statement->fetch();
    }

    public function update(string $id, array $term, string $userId): array
    {
        $statement = $this->connection->prepare(
            'UPDATE academic_terms
             SET name = :name,
                 academic_year = :academic_year,
                 semester = :semester,
                 start_date = :start_date,
                 end_date = :end_date,
                 is_active = :is_active,
                 updated_at = NOW(),
                 updated_by = :updated_by
             WHERE id = :id
             RETURNING id, name, academic_year, semester, start_date, end_date, is_active'
        );
        $statement->execute([
            'id' => $id,
            'name' => $term['name'],
            'academic_year' => $term['academic_year'],
            'semester' => $term['semester'],
            'start_date' => $term['start_date'],
            'end_date' => $term['end_date'],
            'is_active' => $term['is_active'],
            'updated_by' => $userId,
        ]);

        return $statement->fetch();
    }

    public function deactivateOthers(string $activeId, string $userId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE academic_terms
             SET is_active = FALSE, updated_at = NOW(), updated_by = :updated_by
             WHERE id <> :id AND is_active IS TRUE'
        );
        $statement->execute([
            'id' => $activeId,
            'updated_by' => $userId,
        ]);
    }
}
