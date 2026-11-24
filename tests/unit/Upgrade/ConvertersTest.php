<?php

use App\Libraries\Upgrade\ConversionStep;
use App\Libraries\Upgrade\Converters;
use App\Libraries\Upgrade\SchemaStore;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The runner that applies the conversion steps behind the schema version.
 *
 * @internal
 */
final class ConvertersTest extends CIUnitTestCase
{
    private function store(int $version = 0): SchemaStore
    {
        return new class ($version) implements SchemaStore {
            /** @var array<int, int> */
            public array $cursors = [];

            public function __construct(private int $version)
            {
            }

            public function version(): int
            {
                return $this->version;
            }

            public function setVersion(int $version): void
            {
                $this->version = $version;
            }

            public function cursor(int $step): ?int
            {
                return $this->cursors[$step] ?? null;
            }

            public function setCursor(int $step, int $id): void
            {
                $this->cursors[$step] = $id;
            }

            public function clearCursor(int $step): void
            {
                unset($this->cursors[$step]);
            }
        };
    }

    /**
     * @param list<string> $log
     */
    private function step(int $number, array &$log, bool $fails = false): ConversionStep
    {
        return new class ($number, $log, $fails) implements ConversionStep {
            /**
             * @param list<string> $log
             */
            public function __construct(private int $number, private array &$log, private bool $fails)
            {
            }

            public function number(): int
            {
                return $this->number;
            }

            public function name(): string
            {
                return 'step ' . $this->number;
            }

            public function run(SchemaStore $store, bool $dryRun, ?float $deadline = null): array
            {
                $this->log[] = ($dryRun ? 'dry ' : 'run ') . $this->number;
                if ($this->fails) {
                    $store->setCursor($this->number, 40);

                    throw new RuntimeException('boom');
                }

                return ['changed' => $this->number * 10, 'problems' => $dryRun ? [] : ['one problem']];
            }
        };
    }

    public function testStepsRunInOrderAndMoveTheVersionAfterEachOne(): void
    {
        $log    = [];
        $runner = new Converters([$this->step(2, $log), $this->step(1, $log), $this->step(3, $log)]);
        $store  = $this->store();

        $report = $runner->run($store);

        $this->assertSame(['run 1', 'run 2', 'run 3'], $log);
        $this->assertSame(3, $store->version());
        $this->assertSame([1, 2, 3], array_column($report, 'number'));
        $this->assertSame([10, 20, 30], array_column($report, 'changed'));
        $this->assertSame(3, $runner->latest());
        $this->assertFalse($runner->pending($store));
    }

    public function testFinishedStepsAreSkipped(): void
    {
        $log    = [];
        $runner = new Converters([$this->step(1, $log), $this->step(2, $log)]);
        $store  = $this->store(1);

        $this->assertTrue($runner->pending($store));
        $runner->run($store);

        $this->assertSame(['run 2'], $log);
        $this->assertSame(2, $store->version());
        $this->assertSame([], $runner->run($store), 'A second run has nothing to do.');
    }

    public function testAFailingStepStopsTheRunAndKeepsItsCursor(): void
    {
        $log    = [];
        $runner = new Converters([$this->step(1, $log), $this->step(2, $log, true), $this->step(3, $log)]);
        $store  = $this->store();

        try {
            $runner->run($store);
            $this->fail('The failing step should stop the run.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(['run 1', 'run 2'], $log);
        $this->assertSame(1, $store->version(), 'The version stays at the last finished step.');
        $this->assertSame(40, $store->cursor(2), 'The cursor is kept so a rerun resumes.');
        $this->assertTrue($runner->pending($store));
    }

    public function testADryRunReportsEveryPendingStepAndMovesNothing(): void
    {
        $log    = [];
        $runner = new Converters([$this->step(1, $log), $this->step(2, $log)]);
        $store  = $this->store();

        $report = $runner->run($store, true);

        $this->assertSame(['dry 1', 'dry 2'], $log);
        $this->assertSame(0, $store->version());
        $this->assertCount(2, $report);
    }

    public function testNumbersMustBeOneTwoThreeWithNoGap(): void
    {
        $log = [];

        $this->expectException(InvalidArgumentException::class);
        new Converters([$this->step(1, $log), $this->step(3, $log)]);
    }

    /**
     * A step that needs two calls: the first stops at the deadline, the second finishes.
     */
    private function slowStep(int $number, int &$calls): ConversionStep
    {
        return new class ($number, $calls) implements ConversionStep {
            public function __construct(private int $number, private int &$calls)
            {
            }

            public function number(): int
            {
                return $this->number;
            }

            public function name(): string
            {
                return 'slow ' . $this->number;
            }

            public function run(SchemaStore $store, bool $dryRun, ?float $deadline = null): array
            {
                $this->calls++;
                if ($deadline !== null && $this->calls === 1) {
                    $store->setCursor($this->number, 7);

                    return ['changed' => 3, 'problems' => [], 'complete' => false];
                }

                return ['changed' => 2, 'problems' => []];
            }
        };
    }

    public function testAStepThatRunsOutOfTimeLeavesTheVersionAndTheLaterStepsAlone(): void
    {
        $log    = [];
        $calls  = 0;
        $runner = new Converters([$this->step(1, $log), $this->slowStep(2, $calls), $this->step(3, $log)]);
        $store  = $this->store();

        $report = $runner->run($store, false, microtime(true) + 1);

        $this->assertSame(['run 1'], $log, 'The step after the slow one must wait.');
        $this->assertSame(1, $store->version(), 'The version stays at the last finished step.');
        $this->assertSame(7, $store->cursor(2));
        $this->assertSame([1, 2], array_column($report, 'number'));
        $this->assertFalse($report[1]['complete']);
        $this->assertArrayNotHasKey('complete', $report[0]);
    }

    public function testASecondCallFinishesTheStepAndRunsTheRest(): void
    {
        $log    = [];
        $calls  = 0;
        $runner = new Converters([$this->step(1, $log), $this->slowStep(2, $calls), $this->step(3, $log)]);
        $store  = $this->store();

        $runner->run($store, false, microtime(true) + 1);
        $report = $runner->run($store, false, microtime(true) + 1);

        $this->assertSame(['run 1', 'run 3'], $log);
        $this->assertSame(3, $store->version());
        $this->assertSame([2, 3], array_column($report, 'number'));
        $this->assertNull($store->cursor(2), 'A finished step clears its cursor.');
    }

    public function testWithNoDeadlineEveryStepRunsToItsEnd(): void
    {
        $calls  = 0;
        $runner = new Converters([$this->slowStep(1, $calls)]);
        $store  = $this->store();

        $runner->run($store);

        $this->assertSame(1, $store->version());
        $this->assertSame(1, $calls);
    }

    public function testNoStepsMeansNothingIsPending(): void
    {
        $runner = new Converters([]);

        $this->assertSame(0, $runner->latest());
        $this->assertFalse($runner->pending($this->store()));
        $this->assertSame([], $runner->run($this->store()));
    }
}
