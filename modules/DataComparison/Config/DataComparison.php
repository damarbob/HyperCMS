<?php

namespace DataComparison\Config;

use CodeIgniter\Config\BaseConfig;

class DataComparison extends BaseConfig
{
    // Default data sources as a JSON string
    public string $defaultDataSources = '[{"id":"internal","label":"Internal","internal":true,"options":{"url":"api/v1/data-comparison/data","type":"POST"}}]';

    public function __construct()
    {
        parent::__construct();
    }
}
