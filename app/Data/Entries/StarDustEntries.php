<?php

namespace App\Data\Entries;

use StarDust\StarDust;
use StarDust\Write\EntryPayload;

/**
 * EntryEngine over the real StarDust 0.3 engine, for the one tenant every HyperCMS entry belongs to.
 */
class StarDustEntries implements EntryEngine
{
    public function __construct(
        private readonly StarDust $engine,
        private readonly int $tenantId,
    ) {
    }

    public function write(int $sdModelId, array $fields): int
    {
        return $this->engine->write(new EntryPayload($this->tenantId, $sdModelId, $fields))->entryId;
    }

    public function update(int $sdEntryId, array $fields): void
    {
        $this->engine->updateEntry($this->tenantId, $sdEntryId, $fields);
    }

    public function get(int $sdEntryId): ?array
    {
        return $this->engine->get($this->tenantId, $sdEntryId)?->fields;
    }

    public function delete(int $sdEntryId): bool
    {
        return $this->engine->deleteEntry($this->tenantId, $sdEntryId);
    }
}
