<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key
    |--------------------------------------------------------------------------
    |
    | Set your OpenAI API key here. Ensure this value is set in your .env file
    | as OPENAI_API_KEY.
    |
    */

    'api_key' => env('OPENAI_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Base URL (Optional)
    |--------------------------------------------------------------------------
    |
    | If you're using a custom OpenAI-compatible API, you can set the base URL here.
    | Otherwise, it will default to OpenAI's official API.
    |
    */

    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Define any additional settings for the HTTP client, such as timeouts.
    |
    */

    'http' => [
        'timeout' => env('OPENAI_HTTP_TIMEOUT', 30),
    ],
];
