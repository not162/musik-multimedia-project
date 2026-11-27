<?php

return [
    'ai' => [
        'gemini_key'       => env('GEMINI_API_KEY'),
        'openai_key'       => env('OPENAI_API_KEY'),
        'nvidia_key'       => env('NVIDIA_API_KEY'),
        'nvidia_model'     => env('NVIDIA_MODEL', 'nvidia/riva-translate-4b-instruct-v2'),
        'nvidia_base_url'  => env('NVIDIA_BASE_URL', 'https://integrate.api.nvidia.com/v1'),
        'compute_name'     => env('AI_COMPUTE_NAME'),
        'compute_api_key'  => env('AI_COMPUTE_API_KEY'),
        'compute_base_url' => env('AI_COMPUTE_BASE_URL'),
    ],
];
