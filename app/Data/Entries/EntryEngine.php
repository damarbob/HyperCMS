<?php

namespace App\Data\Entries;

use StarDust\Exception\EntryNotFoundException;

/**
 * The four calls EntryWriter makes on StarDust, for one tenant.
 *
 * StarDust's own class is final, so a test cannot stand in for it. This small interface can be replaced
 * by a fake that fails at a chosen step, which is how the step order of every write is tested.
 */
interface EntryEngine
{
    /**
     * Store a new entry and return its StarDust id.
     *
     * @param array<string, mixed> $fields
     */
    public function write(int $sdModelId, array $fields): int;

    /**
     * Replace the whole payload of an entry.
     *
     * @param array<string, mixed> $fields
     *
     * @throws EntryNotFoundException When StarDust has no live entry with this id.
     */
    public function update(int $sdEntryId, array $fields): void;

    /**
     * Read the current payload of a live entry, or null when StarDust has none.
     *
     * @return array<string, mixed>|null
     */
    public function get(int $sdEntryId): ?array;

    /**
     * Soft delete an entry. Returns false when it was already deleted or does not exist.
     */
    public function delete(int $sdEntryId): bool;
}
