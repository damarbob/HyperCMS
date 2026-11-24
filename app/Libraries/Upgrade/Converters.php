<?php

namespace App\Libraries\Upgrade;

use InvalidArgumentException;

/**
 * Runs the conversion steps that are behind the schema version, in order.
 *
 * The version moves up by one only after a whole step is done. A step that throws stops the run, leaves the version
 * where it was, and is run again, from its cursor, the next time. A dry run runs every pending step without moving
 * the version.
 */
final class Converters
{
    /**
     * @var list<ConversionStep>
     */
    private array $steps;

    /**
     * @param list<ConversionStep> $steps In any order. The numbers must be 1, 2, 3 and so on with none missing.
     *
     * @throws InvalidArgumentException When a number repeats or leaves a gap.
     */
    public function __construct(array $steps)
    {
        usort($steps, static fn (ConversionStep $a, ConversionStep $b): int => $a->number() <=> $b->number());

        foreach ($steps as $index => $step) {
            if ($step->number() !== $index + 1) {
                throw new InvalidArgumentException('The conversion steps must be numbered 1, 2, 3 and so on, but "' . $step->name() . '" is number ' . $step->number() . ' at place ' . ($index + 1) . '.');
            }
        }

        $this->steps = $steps;
    }

    /**
     * The version of a database that has had every step.
     */
    public function latest(): int
    {
        return count($this->steps);
    }

    /**
     * Whether the database is behind the latest version.
     */
    public function pending(SchemaStore $store): bool
    {
        return $store->version() < $this->latest();
    }

    /**
     * Run every pending step.
     *
     * With a `$deadline`, the run stops at the first step that cannot finish in time, reports that step with `complete` false
     * and leaves the version where it was. Calling it again resumes from that step's cursor.
     *
     * @return list<array{number: int, name: string, changed: int, problems: list<string>, complete?: bool}> One entry per step that ran.
     */
    public function run(SchemaStore $store, bool $dryRun = false, ?float $deadline = null): array
    {
        $report = [];

        foreach ($this->steps as $step) {
            if ($step->number() <= $store->version()) {
                continue;
            }

            $result   = $step->run($store, $dryRun, $deadline);
            $entry    = ['number' => $step->number(), 'name' => $step->name(), 'changed' => $result['changed'], 'problems' => $result['problems']];
            if (($result['complete'] ?? true) === false) {
                $report[] = $entry + ['complete' => false];

                return $report;
            }
            $report[] = $entry;

            if (! $dryRun) {
                $store->setVersion($step->number());
                $store->clearCursor($step->number());
            }
        }

        return $report;
    }
}
