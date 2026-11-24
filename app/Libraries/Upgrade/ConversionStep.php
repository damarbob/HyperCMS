<?php

namespace App\Libraries\Upgrade;

/**
 * One step that rewrites stored data from an old shape to a new one, after the copy of the old tables.
 *
 * A step skips what it already converted and records its place with `SchemaStore::setCursor()`, so running it again
 * after an interruption finishes the work and changes nothing twice.
 */
interface ConversionStep
{
    /**
     * The place of the step in the order. The schema version is the number of the last step that finished, so
     * numbers start at 1 and never change once released.
     */
    public function number(): int;

    public function name(): string;

    /**
     * Convert the stored data, or only report what would change when `$dryRun` is true.
     *
     * With a `$deadline`, a time from `microtime(true)`, a step that works in chunks stops after the chunk that passes it, keeps its
     * cursor and answers `complete` as false. Calling it again carries on from the cursor.
     *
     * @return array{changed: int, problems: list<string>, complete?: bool} How many rows changed or would change, and what could
     *         not be converted, one line each. `complete` is absent or true when the step reached its end.
     */
    public function run(SchemaStore $store, bool $dryRun, ?float $deadline = null): array;
}
