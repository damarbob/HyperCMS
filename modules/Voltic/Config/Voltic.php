<?php

namespace Voltic\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuration for Voltic AI integration.
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

    // Parsed array of individual model identifiers
    private array $modelsArray = [];

    /**
     * Constructor.
     * Parses the comma-separated models string into an array.
     */
    public function __construct()
    {
        parent::__construct();

        // Split models string into array elements
        $this->modelsArray = explode(',', $this->models);
    }

    /**
     * Retrieve the list of configured AI models.
     *
     * @return array Parsed array of model identifiers
     */
    public function getModels(): array
    {
        return $this->modelsArray;
    }
}
