<?php

namespace App\Libraries\Upgrade;

/**
 * Where the upgrade keeps its place. The schema version says how many conversion steps have finished, and a cursor
 * says how far the step in progress got, so an interrupted upgrade is finished by running it again.
 */
interface SchemaStore
{
    /**
     * The number of the last step that finished, or 0 when none has.
     */
    public function version(): int;

    public function setVersion(int $version): void;

    /**
     * The last row id the step finished, or null when it has not started.
     */
    public function cursor(int $step): ?int;

    public function setCursor(int $step, int $id): void;

    public function clearCursor(int $step): void;
}
