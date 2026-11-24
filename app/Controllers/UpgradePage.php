<?php

namespace App\Controllers;

use App\Libraries\Install\InstallState;
use App\Libraries\Upgrade\Preflight;
use App\Libraries\Upgrade\Source;
use App\Libraries\Upgrade\Steps\Steps;
use App\Libraries\Upgrade\UpgradeJob;
use App\Libraries\Upgrade\UpgradeState;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\HyperEngine;
use Throwable;

/**
 * The upgrade page, for a site whose data is behind the code and that has no shell. It exists only while the data is behind, and
 * only whoever can read the key file in the writable folder can start or continue the upgrade. The work is done in requests
 * of a few seconds each by `UpgradeJob`, and the page asks for the next one until the job is done.
 */
class UpgradePage extends BaseController
{
    /**
     * The session entry that records the upgrade key was typed correctly.
     */
    private const PROVEN = 'upgrade_key_proven';

    public function index(): string|ResponseInterface
    {
        $state = $this->gate();

        if (! $this->proven($state)) {
            $state->key();

            return view('upgrade/key', ['keyPath' => 'writable/' . UpgradeState::KEY, 'error' => '']);
        }

        $job = new UpgradeJob(db_connect(), $state);

        return view('upgrade/index', [
            'started'  => $job->started(),
            'progress' => $job->progress(),
            'report'   => $job->started() ? null : $this->report(),
            'pending'  => $this->pendingSteps(),
            'busy'     => false,
        ]);
    }

    public function key(): string|RedirectResponse|ResponseInterface
    {
        $state = $this->gate();

        if (! $state->keyMatches((string) $this->request->getPost('key'))) {
            return view('upgrade/key', ['keyPath' => 'writable/' . UpgradeState::KEY, 'error' => lang('Upgrade.keyWrong')]);
        }

        session()->set(self::PROVEN, hash('sha256', $state->key()));

        return redirect()->to(site_url('upgrade'));
    }

    /**
     * Do a few seconds of the upgrade, and answer with where it stands. The answer asks for the next call until it is done.
     */
    public function run(): string|RedirectResponse|ResponseInterface
    {
        $state = $this->gate();
        if (! $this->proven($state)) {
            return redirect()->to(site_url('upgrade'));
        }

        // A browser that closes or a service that gives up must not cut a batch short in the middle of a chunk.
        ignore_user_abort(true);

        // The setting is the most a request may work. A host with a shorter PHP time limit gets half of it, so the request ends in time.
        $limit    = (int) ini_get('max_execution_time');
        $budget   = max(0.001, config(HyperEngine::class)->upgradeBatchSeconds);
        $budget   = $limit > 0 ? min($budget, max(3, intdiv($limit, 2))) : $budget;
        $progress = (new UpgradeJob(db_connect(), $state))->advance(microtime(true) + $budget);

        return $this->answer($progress, (bool) ($progress['busy'] ?? false));
    }

    public function restart(): string|RedirectResponse|ResponseInterface
    {
        $state = $this->gate();
        if (! $this->proven($state)) {
            return redirect()->to(site_url('upgrade'));
        }

        (new UpgradeJob(db_connect(), $state))->restart();

        return $this->answer((new UpgradeJob(db_connect(), $state))->progress(), false);
    }

    /**
     * The progress piece alone for a page that asks for it, or the whole page for a browser without scripts.
     *
     * @param array<string, mixed> $progress
     */
    private function answer(array $progress, bool $busy): string
    {
        if ($this->request->hasHeader('HX-Request')) {
            return view('upgrade/partials/progress', ['progress' => $progress, 'busy' => $busy]);
        }

        return view('upgrade/index', ['started' => true, 'progress' => $progress, 'report' => null, 'pending' => [], 'busy' => $busy]);
    }

    /**
     * The state of the site, or a 404 when the page must not exist. No page leaks the reason.
     */
    private function gate(): UpgradeState
    {
        $state = UpgradeState::current();

        if (InstallState::current()->open() || $state->needed(db_connect(), Steps::LATEST) !== true) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $state;
    }

    private function proven(UpgradeState $state): bool
    {
        return hash_equals(hash('sha256', $state->key()), (string) session(self::PROVEN));
    }

    /**
     * What the preflight found in the old tables. Null when the site holds no old layout, which is a site that is only behind
     * on conversions, or when the tables cannot be read.
     *
     * @return array<string, mixed>|null
     */
    private function report(): ?array
    {
        $db     = db_connect();
        $source = new Source($db);
        if ($source->missingTables() !== []) {
            return null;
        }

        try {
            return (new Preflight($db, $source, service('stardust')))->run();
        } catch (Throwable $e) {
            log_message('error', 'The upgrade report could not be made. ' . $e->getMessage());

            return null;
        }
    }

    /**
     * The names of the conversion steps that are still to run.
     *
     * @return list<string>
     */
    private function pendingSteps(): array
    {
        $db      = db_connect();
        $version = 0;
        if ($db->tableExists('hyper_counters')) {
            $version = (new \App\Libraries\Upgrade\CounterSchemaStore($db))->version();
        }

        $names = [];

        try {
            foreach (Steps::make($db) as $step) {
                if ($step->number() > $version) {
                    $names[] = $step->name();
                }
            }
        } catch (Throwable) {
            // The steps need tables an old site does not have yet, and the list is only a hint.
        }

        return $names;
    }
}
