<?php

declare(strict_types=1);

final class AcademicEventRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function findTerm(string $id): ?array
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

    public function activeTerm(): ?array
    {
        $term = $this->connection->query(
            'SELECT id, name, academic_year, semester, start_date, end_date, is_active
             FROM academic_terms
             WHERE is_active IS TRUE
             ORDER BY start_date DESC NULLS LAST
             LIMIT 1'
        )->fetch();

        return is_array($term) ? $term : null;
    }

    public function find(string $id): ?array
    {
        $statement = $this->connection->prepare($this->selectQuery() . ' WHERE e.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $event = $statement->fetch();

        return is_array($event) ? $event : null;
    }

    public function all(?string $termId, ?string $role, ?string $startsAt, ?string $endsAt): array
    {
        $conditions = [];
        $parameters = [];

        if ($termId !== null) {
            $conditions[] = 'e.term_id = :term_id';
            $parameters['term_id'] = $termId;
        }
        if ($role !== null) {
            $conditions[] = "e.audience IN ('all', :role)";
            $parameters['role'] = $role;
        }
        if ($startsAt !== null) {
            $conditions[] = 'e.ends_at >= :starts_at';
            $parameters['starts_at'] = $startsAt;
        }
        if ($endsAt !== null) {
            $conditions[] = 'e.starts_at <= :ends_at';
            $parameters['ends_at'] = $endsAt;
        }

        $query = $this->selectQuery();
        if ($conditions !== []) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $query .= ' ORDER BY e.starts_at ASC, e.created_at DESC';
        $statement = $this->connection->prepare($query);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function create(array $event, string $userId): string
    {
        $statement = $this->connection->prepare(
            'INSERT INTO academic_events (term_id, title, description, starts_at, ends_at, location, audience, created_by)
             VALUES (:term_id, :title, :description, :starts_at, :ends_at, :location, :audience, :created_by)
             RETURNING id'
        );
        $statement->execute($event + ['created_by' => $userId]);

        return (string) $statement->fetchColumn();
    }

    public function update(string $id, array $event, string $userId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE academic_events
             SET term_id = :term_id,
                 title = :title,
                 description = :description,
                 starts_at = :starts_at,
                 ends_at = :ends_at,
                 location = :location,
                 audience = :audience,
                 updated_at = NOW(),
                 updated_by = :updated_by
             WHERE id = :id'
        );
        $statement->execute($event + ['id' => $id, 'updated_by' => $userId]);
    }

    public function delete(string $id): void
    {
        $statement = $this->connection->prepare('DELETE FROM academic_events WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    private function selectQuery(): string
    {
        return 'SELECT e.id, e.term_id, e.title, e.description, e.starts_at, e.ends_at, e.location, e.audience, e.created_at, e.updated_at,
                       term.name AS term_name, term.academic_year, term.semester, term.start_date AS term_start_date, term.end_date AS term_end_date
                FROM academic_events e
                INNER JOIN academic_terms term ON term.id = e.term_id';
    }
}
