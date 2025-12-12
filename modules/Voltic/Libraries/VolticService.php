<?php

namespace Voltic\Libraries;

use StarDust\Models\ModelsModel;
use Config\Hyper;
use Voltic\Config\Voltic as ConfigVoltic;

// Service class that orchestrates AI prompt construction and execution
class VolticService
{
    // Configuration settings for Voltic (API key, endpoint, models)
    protected ConfigVoltic $config;

    // Hyper CMS configuration (environment, module lists, etc.)
    protected Hyper $hyperConfig;

    // Logger for recording operations and errors
    protected $logger;

    // Model for retrieving metadata definitions from the database
    protected ModelsModel $modelsModel;

    // Manager responsible for creating and managing AI prompts
    public VolticPromptManager $promptManager;

    // Raw JSON of available AI models
    protected $modelsJson;

    // Minified JSON for lightweight model listing
    protected $modelsJsonMin;

    // JSON payload of existing system entries for context
    protected $entriesJson;

    public function __construct()
    {
        $this->config = config('Voltic');
        $this->hyperConfig = config('Hyper');
        /** @var \CodeIgniter\Log\Logger */
        $this->logger = service('logger');

        /** @var ModelsModel */
        $this->modelsModel = model('modelsModel');

        // Reference data
        $models = service('modelsManager')->get();
        $entries = service('entriesManager')->get();

        $filteredModels = array_map(function ($item) {
            // Define only the allowed keys
            $allowedKeys = ['id', 'name', 'fields'];
            // Create an associative array of allowed keys
            $allowed = array_flip($allowedKeys);
            // Return only the intersections of $item with allowed keys
            $result = array_intersect_key($item, $allowed);

            // Process the 'fields' if it exists.
            if (isset($result['fields'])) {
                // Decode the JSON string to an associative array.
                $decodedFields = json_decode($result['fields'], true);

                if (is_array($decodedFields)) {
                    // Map each field to retain only the 'id', 'type', and 'className' keys.
                    $decodedFields = array_map(function ($field) {
                        $allowedFieldKeys = ['id', 'type', 'className'];
                        return array_intersect_key($field, array_flip($allowedFieldKeys));
                    }, $decodedFields);
                }

                $result['fields'] = $decodedFields;
            }

            return $result;
        }, $models);

        $this->modelsJson = json_encode($filteredModels);

        $filteredModels = array_map(function ($item) {
            // Define only the allowed keys
            $allowedKeys = ['id', 'name'];
            // Create an associative array of allowed keys
            $allowed = array_flip($allowedKeys);
            // Return only the intersections of $item with allowed keys
            return array_intersect_key($item, $allowed);
        }, $models);

        $this->modelsJsonMin = json_encode($filteredModels);

        // Entries with filtered attributes
        $filteredEntries = array_map(function ($item) {
            // Define only the allowed keys
            $allowedKeys = ['id', 'model_id', 'fields'];
            // Create an associative array of allowed keys
            $allowed = array_flip($allowedKeys);
            // Return only the intersections of $item with allowed keys
            $result = array_intersect_key($item, $allowed);

            // Process the 'fields' if it exists.
            if (isset($result['fields'])) {
                // Decode the JSON string to an associative array.
                $decodedFields = json_decode($result['fields'], true);

                if (
                    !empty($decodedFields) &&
                    is_array($decodedFields) &&
                    !empty($decodedFields[0]['value'])
                ) {
                    $value = $decodedFields[0]['value'];

                    // if more than 30 chars, cut + ellipsis
                    if (mb_strlen($value, 'UTF-8') > 30) {
                        $value = mb_substr($value, 0, 30, 'UTF-8') . '...';
                    }

                    // keep only the first field, with trimmed value
                    $decodedFields = [
                        array_merge(
                            $decodedFields[0],
                            ['value' => $value]
                        )
                    ];
                }

                $result['fields'] = $decodedFields;
            }

            return $result;
        }, $entries);

        $this->entriesJson = json_encode($filteredEntries);

        $this->promptManager = new VolticPromptManager(
            $this->modelsJson,
            $this->modelsJsonMin,
            $this->entriesJson
        );
    }

    public function ask(array $prompt, ?array $systemPrompt = null): ?array
    {
        if (!$systemPrompt) {
            $systemPrompt = $this->promptManager->prompt(); // Use original prompt
        }

        array_push($prompt, $systemPrompt);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config->apiKey, // OpenRouter (remote)
            'Accept: application/json'
        ];

        // Get the list of AI models from configuration.
        $aiModels = $this->selectRandomAiModelAndRemaining($this->config->getModels());
        $chosenAiModel = $aiModels['chosen'];
        $remainingAiModels = $aiModels['remaining'];

        $requestData = [
            'model' => $chosenAiModel,
            'models' => $remainingAiModels, // Fallback models
            'messages' => $prompt,
            "response_format" => [
                "type" => "json_object"
            ],
            'max_tokens' => $this->config->maxTokens,
            'temperature' => 0.5,
            'stream' => false,
        ];

        $postData = json_encode($requestData);

        log_message('debug', "Sending POST request to AI: " . $postData);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->config->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_TIMEOUT => $this->config->timeout,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING => ''
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            $this->logger->error("AI model cURL Error: {$error}");
            return null;
        }

        if ($httpCode !== 200) {
            $this->logger->error("AI model API HTTP {$httpCode}");
            return null;
        }

        // Mock response
        // $response = trim(<<<'TEXT'
        // {"id":"gen-1749786578-C9jjzwQ49OLsqeIAsD6e","provider":"Chutes","model":"microsoft/mai-ds-r1:free","object":"chat.completion","created":1749786578,"choices":[{"logprobs":null,"finish_reason":"stop","native_finish_reason":"stop","index":0,"message":{"role":"assistant","content":"{\n    \"actions\": [\n        {\n            \"type\": \"show-entry\",\n            \"params\": [\n                {\n                    \"id\": \"92\"\n                }\n            ]\n        },\n        {\n            \"type\": \"show-entry\",\n            \"params\": [\n                {\n                    \"id\": \"93\"\n                }\n            ]\n        }\n    ],\n    \"message\": \"Here are entries related to stars in Hyper CMS:\\n\\n• **UY Scuti: The Largest Star in ...** (Entry #92)\\n• **The Sun: Our Star and Its Impo...** (Entry #93)\\n\\nI've triggered the detailed view for these entries.\"\n}","refusal":null,"reasoning":"Okay, the user asked to show any entries about stars. Let me check the existing entries to find relevant ones. Looking through the entries array, I need to filter those that mention \"star\" or \"stars\" in their fields. \n\nFirst, I'll go through each entry. Entry 92 has the title \"UY Scuti: The Largest Star in ...\", which clearly mentions a star. Entry 93 is \"The Sun: Our Star and Its Impo...\", the Sun is a star, so that's relevant. Entry 94 is \"Exploring Black Holes: A Cosmi...\", which might relate to stars but isn't directly about stars. Entry 96 is a draft titled \"Understanding Quasars: The Mos...\", quasars are related to stars but not exactly the same. \n\nSo, entries 92 and 93 are the main ones about stars. There's also entry 92 again in model 8, but the title is truncated. I should make sure to include those. Let me confirm the model and fields for these entries. Both are in model 8 (Draft) with the title field. \n\nNow, I need to format the response with these entries. The user might want to see the full details, so using the show-entry action for each relevant entry ID. The entry IDs are 92, 93. Wait, looking back, entry 93 is in model 8 with id 93, title about the Sun. Entry 92 is id 92 in model 8. \n\nSo, the actions should be two show-entry actions for IDs 92 and 93. The message should list the entries found. I need to structure the JSON response with these actions and the message. Make sure the message uses markdown bullets and mentions Hyper CMS. Double-check the existing entries list to confirm no others are missed. Alright, that's it.\n"}}],"usage":{"prompt_tokens":4062,"completion_tokens":524,"total_tokens":4586}}
        // TEXT);

        log_message('debug', 'Voltic raw response: ' . $response);

        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message('debug', 'JSON Error: ' . json_last_error_msg());
            // Optionally, output the error message for debugging:
            echo 'JSON Error: ' . json_last_error_msg();
        }

        log_message('debug', 'Voltic response: ' . json_encode($responseData, JSON_PRETTY_PRINT));

        // Get the responder's model
        $model = $responseData['model'];

        // Get the Voltic's response message
        $rawReasoning = $responseData['choices'][0]['message']['reasoning'];
        $rawContent = $responseData['choices'][0]['message']['content'];
        // $rawContent = $responseData['message']['content']; // Llama3.2 (local)

        if (empty($rawContent) && empty($rawReasoning)) {
            return [
                "model" => $model,
                "error" => [
                    "message" => lang('Voltic.volticUnableToRespondRightNow')
                ]
            ];
        }

        $finalResponse = json_decode($rawContent, true);
        $finalResponse['model'] = $model;
        $finalResponse['reasoning'] = $rawReasoning;

        if (json_last_error() === JSON_ERROR_NONE) {
            log_message('debug', "Voltic ({$model}) JSON response: " . json_encode($finalResponse, JSON_PRETTY_PRINT));
            log_message('debug', "Voltic ({$model}) JSON response (one-line): " . json_encode($finalResponse));

            if (!empty($response['context'])) {
                log_message('debug', "Voltic ({$model}) JSON response (context): " . json_encode($finalResponse['context']));
            }
            return $finalResponse;
        }

        $finalResponse = json_decode(stripslashes($rawContent), true); // Llama3.2 (local)
        $finalResponse['model'] = $model;
        $finalResponse['reasoning'] = $rawReasoning;

        if (json_last_error() === JSON_ERROR_NONE) {
            log_message('debug', "Voltic ({$model}) JSON response: " . json_encode($finalResponse, JSON_PRETTY_PRINT));
            log_message('debug', "Voltic ({$model}) JSON response (one-line): " . json_encode($finalResponse));
            return $finalResponse;
        }

        // Extract JSON from markdown code block
        $pattern = '/```json\s*(\{.*?\})\s*```/s';
        preg_match($pattern, $rawContent, $matches);

        if (!$matches || !isset($matches[1])) {
            $this->logger->error('JSON markdown code block not found in response. Trying to find normal code block.');

            // Try to extract JSON from normal markdown code block
            $pattern = '/```\s*(\{.*?\})\s*```/s';
            preg_match($pattern, $rawContent, $matches);

            if (!$matches || !isset($matches[1])) {
                $this->logger->error('Normal markdown code block not found in response. Returning raw content.');

                return [
                    "model" => $model,
                    "message" => $rawContent,
                    "reasoning" => $rawReasoning,
                    "error" => [
                        "message" => lang('Voltic.jsonCodeBlockNotFoundInResponse')
                    ]
                ];
            }
        }

        $finalResponse = json_decode($matches[1], true);
        $finalResponse['model'] = $model;
        $finalResponse['reasoning'] = $rawReasoning;

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMsg = 'Voltic: Response JSON parse error. ' . json_last_error_msg();
            $this->logger->error($errorMsg);
            return [
                "model" => $model,
                "message" => $rawContent,
                "reasoning" => $rawReasoning,
                "error" => [
                    "message" => lang('Voltic.xUnableToParseVolticResponse', ['x' => $this->hyperConfig->appName]),
                    "trace" => json_last_error_msg(),
                ]
            ];
        }

        log_message('debug', "Voltic ({$model}) JSON response: " . json_encode($finalResponse, JSON_PRETTY_PRINT));
        log_message('debug', "Voltic ({$model}) JSON response (one-line): " . json_encode($finalResponse));

        return $finalResponse;
    }

    /**
     * Select a random model and return it along with a limited set
     * of remaining models (shuffled). The original array remains unchanged.
     * 
     * A time-based seed ensures that the randomness depends on the current time.
     *
     * @param array $models Array of AI model strings.
     * @param int   $limit  The maximum number of remaining models to return (default 3).
     * @return array Returns an associative array with keys "chosen" and "remaining".
     */
    function selectRandomAiModelAndRemaining(array $models, int $limit = 3): array
    {
        // Return default values if the array is empty.
        if (empty($models)) {
            return ['chosen' => null, 'remaining' => []];
        }

        // Seed the random number generator using the current time in milliseconds.
        // (Be cautious: reseeding too frequently can affect overall randomness in the request.)
        $seed = (int) (microtime(true) * 1000);
        mt_srand($seed);

        // Randomly choose a model index.
        $chosenIndex = array_rand($models);
        $chosenModel = $models[$chosenIndex];

        // Create a new array with all models except the chosen one.
        $remainingModels = array_filter($models, function ($item, $key) use ($chosenIndex) {
            return $key !== $chosenIndex;
        }, ARRAY_FILTER_USE_BOTH);

        // Re-index the array to ensure numeric keys.
        $remainingModels = array_values($remainingModels);

        // Shuffle the remaining models.
        shuffle($remainingModels);

        // Limit the remaining models to the specified limit.
        $remainingModels = array_slice($remainingModels, 0, $limit);

        return [
            'chosen' => $chosenModel,
            'remaining' => $remainingModels
        ];
    }

    public function askMock($prompt)
    {
        // Reserved for mock responses
    }
}
