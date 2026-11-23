<?php

namespace Voltic\Libraries;

use CodeIgniter\Shield\Entities\User;
use Voltic\Config\Voltic as VolticConfig;

/**
 * Talks to the AI service. It builds what the AI may know for one person, the models and the entries that person may read,
 * sends the conversation, and reads the answer into a message and a list of proposed actions.
 */
class VolticService
{
    private VolticConfig $config;

    public function __construct(private readonly User $user, ?VolticConfig $config = null)
    {
        $this->config = $config ?? config('Voltic');
    }

    /**
     * The models the person may read, with the fields each has, and a sample of their entries. Nothing else is sent to the
     * AI service.
     *
     * @return array{models: list<array<string, mixed>>, entries: list<array<string, mixed>>, can: array<string, bool>}
     */
    public function context(): array
    {
        $access  = service('modelAccess');
        $models  = [];
        $entries = [];
        $can     = ['create_model' => can('models.manage')];
        $left    = $this->config->entriesInAll;

        foreach (service('modelStore')->allArray() as $model) {
            $id = (int) $model['id'];
            if (! $access->canRead($this->user, $id)) {
                continue;
            }

            $fields = [];
            foreach (json_decode((string) ($model['fields'] ?? '[]'), true) ?: [] as $field) {
                if (! is_array($field) || ! isset($field['id'])) {
                    continue;
                }
                $item = ['id' => $field['id'], 'label' => $field['label'] ?? $field['id'], 'type' => $field['type'] ?? 'text'];
                if (isset($field['choices']) && is_array($field['choices'])) {
                    $item['choices'] = $field['choices'];
                }
                $fields[] = $item;
            }
            $models[]               = ['id' => $id, 'name' => (string) $model['name'], 'fields' => $fields];
            $can['create_entry_' . $id] = can('entries.create', $id);

            if ($left <= 0) {
                continue;
            }
            $labelField = (string) ($model['label_field'] ?? '');
            foreach (array_slice(service('entryStore')->recent(['model_id' => $id, 'hidden_model_ids' => []]), 0, min($left, $this->config->entriesPerModel)) as $row) {
                $values = json_decode((string) ($row['fields'] ?? ''), true);
                $label  = is_array($values) && $labelField !== '' && isset($values[$labelField]) && is_scalar($values[$labelField]) ? trim((string) $values[$labelField]) : '';
                $entries[] = ['id' => (int) $row['id'], 'model_id' => $id, 'label' => mb_strimwidth($label, 0, 60, '...')];
                $left--;
            }
        }

        return ['models' => $models, 'entries' => $entries, 'can' => $can];
    }

    /**
     * Send the conversation and read the answer.
     *
     * @param list<array{role: string, content: string}> $messages The last messages, oldest first.
     *
     * @return array{message?: string, actions?: list<mixed>, model?: string, reasoning?: string, error?: string}
     */
    public function ask(array $messages): array
    {
        $context = $this->context();
        $prompt  = (new VolticPromptManager($context['models'], $context['entries'], $context['can'], (string) $this->user->username))->prompt();

        [$chosen, $fallbacks] = $this->pickModels($this->config->getModels());
        $payload              = json_encode([
            'model'           => $chosen,
            'models'          => $fallbacks,
            'messages'        => array_merge([$prompt], $messages),
            'response_format' => ['type' => 'json_object'],
            'max_tokens'      => $this->config->maxTokens,
            'temperature'     => 0.5,
            'stream'          => false,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $this->config->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . $this->config->apiKey],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => $this->config->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->config->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->config->verifySsl ? 2 : 0,
            CURLOPT_ENCODING       => '',
        ]);
        $body   = curl_exec($curl);
        $failed = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // What is logged is the outcome, never the conversation or the key.
        if ($failed !== '' || $status !== 200 || ! is_string($body)) {
            log_message('error', 'The AI service did not answer. HTTP {status}. {error}', ['status' => $status, 'error' => $failed]);

            return ['error' => lang('Voltic.volticUnableToRespondRightNow')];
        }

        $answer = json_decode($body, true);
        $model  = (string) ($answer['model'] ?? $chosen);
        $reason = (string) ($answer['choices'][0]['message']['reasoning'] ?? '');
        $text   = $answer['choices'][0]['message']['content'] ?? '';
        if (! is_string($text) || trim($text) === '') {
            return ['model' => $model, 'error' => lang('Voltic.volticUnableToRespondRightNow')];
        }

        $parsed = $this->readContent($text);
        if ($parsed === null) {
            // The AI did not follow the format, so what it said is shown as it is.
            return ['model' => $model, 'reasoning' => $reason, 'message' => $text, 'actions' => []];
        }

        return ['model' => $model, 'reasoning' => $reason, 'message' => (string) ($parsed['message'] ?? ''), 'actions' => is_array($parsed['actions'] ?? null) ? array_values($parsed['actions']) : []];
    }

    /**
     * The JSON object in the text of an answer, as the AI sent it or inside a code fence.
     *
     * @return array<string, mixed>|null
     */
    private function readContent(string $text): ?array
    {
        $direct = json_decode($text, true);
        if (is_array($direct) && ! array_is_list($direct)) {
            return $direct;
        }
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $text, $found) === 1) {
            $fenced = json_decode($found[1], true);
            if (is_array($fenced) && ! array_is_list($fenced)) {
                return $fenced;
            }
        }

        return null;
    }

    /**
     * One AI model to ask first, and up to three more that the service may fall back on.
     *
     * @param list<string> $models
     *
     * @return array{0: string, 1: list<string>}
     */
    private function pickModels(array $models): array
    {
        if ($models === []) {
            return ['', []];
        }
        shuffle($models);

        return [array_shift($models), array_slice($models, 0, 3)];
    }
}
