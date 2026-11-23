<?php

namespace Tests\Support;

/**
 * A stand-in for the `files` service that holds rows in memory, so a test of a field needs no database.
 */
final class StubFiles
{
    /**
     * @param array<int, array<string, mixed>> $rows The files by id, each with at least `id` and `name`.
     */
    public function __construct(private readonly array $rows = [])
    {
    }

    public function find(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, array<string, mixed>>
     */
    public function findMany(array $ids): array
    {
        $found = [];
        foreach ($ids as $id) {
            if (isset($this->rows[(int) $id])) {
                $found[(int) $id] = $this->rows[(int) $id];
            }
        }

        return $found;
    }
}
