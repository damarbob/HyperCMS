<?php

namespace Voltic\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuration for Voltic AI integration. Each value can be set in `.env`, for example `Voltic.apiKey = '...'`.
 */
class Voltic extends BaseConfig
{
    // API Key used for authenticating requests to the AI service
    public string $apiKey = 'YOUR_API_KEY';

    // URL of the AI API endpoint
    public string $apiUrl = 'https://example.com/your-ai-api-endpoint';

    // Comma-separated list of AI model names
    public string $models = 'your_ai_model';

    // Maximum tokens allowed per AI response
    public int $maxTokens = 7200;

    // Request timeout in seconds
    public int $timeout = 600;

    // Whether the certificate of the AI service is checked. Turn it off only for a service on your own network.
    public bool $verifySsl = true;

    // The most entries of one model, and in all, that are sent as context
    public int $entriesPerModel = 25;
    public int $entriesInAll = 150;

    /**
     * The configured AI models, one identifier each.
     *
     * @return list<string>
     */
    public function getModels(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->models)), static fn (string $name): bool => $name !== ''));
    }
}
