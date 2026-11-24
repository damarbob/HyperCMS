<?php

namespace App\Controllers;

use App\Libraries\TickKey;
use App\Libraries\TickRunner;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use Config\HyperEngine;

/**
 * Runs one bounded pass of the background work when a scheduled URL fetch asks for it, for a host that has no cron line.
 * The address needs the secret in the `hyperengine.tickSecret` setting, sent as `key` in the address or in the `X-Tick-Key` header.
 * With no secret set the address answers 404. A site uses this or `php spark hyper:tick`, not both.
 */
class BackgroundTick extends BaseController
{
    /**
     * A fetch service waits about a minute before it gives up, so the run stops before that.
     */
    private const MAX_BUDGET_SECONDS = 50;

    public function run(): ResponseInterface
    {
        $config = config(HyperEngine::class);

        if (! TickKey::enabled($config->tickSecret)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $given = $this->request->getHeaderLine('X-Tick-Key');
        if ($given === '') {
            $given = (string) $this->request->getGet('key');
        }

        if (! TickKey::matches($config->tickSecret, $given)) {
            return $this->plain(403, "The key is wrong.\n");
        }

        // A fetch service that gives up and closes the connection must not cut the run short.
        ignore_user_abort(true);

        // StarDust also holds the budget under PHP's own time limit.
        $outcome = (new TickRunner())->run(min($config->tickBudgetSeconds, self::MAX_BUDGET_SECONDS));

        // What went wrong goes to the log. The answer only says that something did, since the address is reachable from outside.
        foreach ($outcome->failures as $failure) {
            log_message('error', 'Tick address. ' . $failure);
        }
        if ($outcome->failed()) {
            return $this->plain(500, implode("\n", $outcome->lines) . "\nThe tick failed. The server log has the details.\n");
        }

        return $this->plain(200, implode("\n", $outcome->lines) . "\n");
    }

    private function plain(int $status, string $body): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setContentType('text/plain', 'utf-8')
            ->setHeader('Cache-Control', 'no-store')
            ->setBody($body);
    }
}
