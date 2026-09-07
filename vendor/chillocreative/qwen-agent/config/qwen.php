<?php

return [

    // Dev-tooling credential for the Qwen coding sub-agent — kept in .env,
    // not in any DB-backed settings table, since this is a developer/CI
    // concern rather than an admin-configurable product feature.
    'api_key' => env('QWEN_API_KEY'),

    'base_url' => env('QWEN_BASE_URL', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1'),

    'model' => env('QWEN_MODEL', 'qwen3.7-flash'),

];
