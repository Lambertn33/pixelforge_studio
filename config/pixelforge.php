<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pixelforge API
    |--------------------------------------------------------------------------
    |
    | Server-side credentials and HTTP behavior for the Pixelforge provider.
    | Never expose these values to the browser or Inertia shared props.
    |
    */

    'base_url' => env('PIXELFORGE_BASE_URL', 'https://pixelforge.promptedit.dev/v1'),

    'api_key' => env('PIXELFORGE_API_KEY'),

    'webhook_secret' => env('PIXELFORGE_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Reject signed deliveries whose timestamp is older (or newer) than this
    | many seconds to limit replay attacks.
    |
    */

    'webhook_tolerance_seconds' => (int) env('PIXELFORGE_WEBHOOK_TOLERANCE_SECONDS', 300),

    /*
    |--------------------------------------------------------------------------
    | HTTP client
    |--------------------------------------------------------------------------
    */

    'http' => [
        'connect_timeout' => (float) env('PIXELFORGE_CONNECT_TIMEOUT', 5),
        'timeout' => (float) env('PIXELFORGE_TIMEOUT', 30),
        'retry' => [
            'times' => (int) env('PIXELFORGE_RETRY_TIMES', 3),
            'sleep_milliseconds' => (int) env('PIXELFORGE_RETRY_SLEEP_MS', 200),
        ],
    ],

];
