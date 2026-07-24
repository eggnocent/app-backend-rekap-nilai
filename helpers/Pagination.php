<?php

declare(strict_types=1);

/**
 * Paginasi limit/offset, mengikuti pola Filter pada app-durna.
 *
 * Perbedaan yang disengaja dari durna: bila query `limit` TIDAK dikirim,
 * paginasi dimatikan dan seluruh baris dikembalikan. Ini menjaga konsumen
 * lama tetap benar — dropdown pada formulir (mata kuliah, dosen, semester)
 * membutuhkan daftar penuh, bukan 10 baris pertama. Halaman bertabel
 * mengaktifkan paginasi secara eksplisit dengan mengirim `limit`.
 */
final class Pagination
{
    public const DEFAULT_LIMIT = 10;
    public const MAX_LIMIT = 100;

    private function __construct(
        public readonly ?int $limit,
        public readonly int $offset,
    ) {
    }

    public static function fromRequest(): self
    {
        $rawLimit = Request::query('limit');
        $rawOffset = Request::query('offset');

        if ($rawLimit === null) {
            return new self(null, 0);
        }

        $limit = filter_var($rawLimit, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1) {
            $limit = self::DEFAULT_LIMIT;
        }
        $limit = min($limit, self::MAX_LIMIT);

        $offset = filter_var($rawOffset ?? '0', FILTER_VALIDATE_INT);
        if ($offset === false || $offset < 0) {
            $offset = 0;
        }

        return new self($limit, $offset);
    }

    /** Paginasi dimatikan — untuk pemanggil internal yang butuh seluruh baris. */
    public static function none(): self
    {
        return new self(null, 0);
    }

    public function enabled(): bool
    {
        return $this->limit !== null;
    }

    /**
     * Tambahkan klausa LIMIT/OFFSET. Nilainya sudah divalidasi sebagai
     * integer sehingga aman disisipkan langsung.
     */
    public function apply(string $query): string
    {
        if ($this->limit === null) {
            return $query;
        }

        return $query . ' LIMIT ' . $this->limit . ' OFFSET ' . $this->offset;
    }

    /** Jumlah seluruh baris yang cocok filter, mengabaikan limit/offset. */
    public static function total(PDO $connection, string $baseQuery, array $parameters): int
    {
        $statement = $connection->prepare('SELECT COUNT(*) FROM (' . $baseQuery . ') AS paginated_total');
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    /** Amplop respons: data halaman ini + total penuh, seperti durna. */
    public function envelope(array $rows, int $total): array
    {
        return [
            'data' => $rows,
            'total' => $total,
            'limit' => $this->limit,
            'offset' => $this->offset,
        ];
    }
}
