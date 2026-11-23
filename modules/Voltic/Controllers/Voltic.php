<?php

namespace Voltic\Controllers;

use App\Modules\ModuleController;
use RuntimeException;
use Voltic\Libraries\Actions;
use Voltic\Libraries\VolticService;

/**
 * The chat. A question goes to the AI service and comes back as a message and proposed actions, which are only shown. An
 * action runs on a second request, when the person confirms it, and runs under their own abilities.
 */
class Voltic extends ModuleController
{
    /** The longest message a person may send, in characters, and the most messages of the conversation that are sent along. */
    private const MAX_LENGTH   = 4000;
    private const MAX_MESSAGES = 10;

    protected $helpers = ['hyper_url'];

    public function index()
    {
        $labels = [];
        foreach (['helloVoltic', 'introduction', 'reasoning', 'volticExperiencingIssuesx', 'failedToGetResponseFromVoltic', 'run', 'ran', 'refused', 'moduleName'] as $key) {
            $labels[$key] = lang('Voltic.' . $key);
        }

        return $this->page('Voltic\Views\voltic', [
            'title' => lang('Voltic.moduleName'),
            'chat'  => [
                'username' => (string) auth()->user()->username,
                'urls'     => ['ask' => base_url('admin/voltic/ask'), 'run' => base_url('admin/voltic/run')],
                'csrf'     => ['name' => csrf_token(), 'hash' => csrf_hash()],
                'labels'   => $labels,
            ],
        ]);
    }

    /**
     * Send the conversation to the AI service and answer with its message and the actions it proposes.
     */
    public function ask()
    {
        $posted   = $this->request->getJSON(true);
        $messages = [];
        foreach (is_array($posted['messages'] ?? null) ? array_slice($posted['messages'], -self::MAX_MESSAGES) : [] as $message) {
            $role    = is_array($message) ? ($message['role'] ?? '') : '';
            $content = is_array($message) ? ($message['content'] ?? '') : '';
            if (in_array($role, ['user', 'assistant'], true) && is_string($content) && trim($content) !== '') {
                $messages[] = ['role' => $role, 'content' => mb_substr($content, 0, self::MAX_LENGTH)];
            }
        }
        if ($messages === [] || end($messages)['role'] !== 'user') {
            return $this->json(['status' => 'error', 'message' => lang('Voltic.messageCannotBeEmpty')], 422);
        }

        $user    = auth()->user();
        $answer  = (new VolticService($user))->ask($messages);
        $actions = new Actions($user);

        // The proposals the person sees: each one read into its plain shape, with a line that says what it does and why it
        // cannot run, if it cannot.
        $proposals = [];
        foreach ($answer['actions'] ?? [] as $raw) {
            $action = $actions->normalise($raw);
            if ($action === null) {
                continue;
            }
            $proposals[] = ['action' => $action, 'text' => $actions->describe($action), 'refusal' => $actions->refusal($action)];
        }

        if (isset($answer['error']) && ! isset($answer['message'])) {
            return $this->json(['status' => 'error', 'message' => $answer['error']], 502);
        }

        return $this->json([
            'status'    => 'success',
            'message'   => (string) ($answer['message'] ?? ''),
            'reasoning' => (string) ($answer['reasoning'] ?? ''),
            'model'     => (string) ($answer['model'] ?? ''),
            'proposals' => $proposals,
        ]);
    }

    /**
     * Run one action the person confirmed.
     */
    public function run()
    {
        $user    = auth()->user();
        $actions = new Actions($user);
        $text    = $this->request->getPost('action');
        $action  = $actions->normalise(is_string($text) ? json_decode($text, true) : null);
        if ($action === null) {
            return $this->json(['status' => 'error', 'message' => lang('Voltic.actionNotUnderstood')], 422);
        }

        try {
            return $this->json(['status' => 'success'] + $actions->run($action));
        } catch (RuntimeException $e) {
            return $this->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            log_message('error', 'A Voltic action failed. {message}', ['message' => $e->getMessage()]);

            return $this->json(['status' => 'error', 'message' => lang('Voltic.actionFailed')], 500);
        }
    }
}
