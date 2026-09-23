<?php

return [
    'ai_enabled' => env('CONTRACTOR_AI_ENABLED', false),
    'ai_provider' => env('CONTRACTOR_AI_PROVIDER', 'openai'),
    'ai_model' => env('CONTRACTOR_AI_MODEL'),
    'cache_store' => env('CONTRACTOR_CACHE_STORE', 'file'),
];
