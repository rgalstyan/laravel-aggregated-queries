<?php

declare(strict_types=1);

return [
    'default_hydrator' => 'array',
    'fallback_to_eloquent' => false,
    'log_queries' => false,
    'log_fallbacks' => true,
    'cache_enabled' => false,
    'cache_ttl' => 3600,
    'max_relations' => 15,
    'strict_mode' => false,
    
    // Soft limit validation
    'max_limit' => 500,
    'strict_limit_validation' => false, // If true, throw exception; if false, log warning
    
    'supported_databases' => ['mysql', 'pgsql'],
    'minimum_versions' => [
        'mysql' => '8.0',
        'pgsql' => '12.0',
    ],
];
