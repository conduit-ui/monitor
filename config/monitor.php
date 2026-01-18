<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Heartbeat Endpoint
    |--------------------------------------------------------------------------
    |
    | URL to POST heartbeat data to. Can be Laravel Cloud, knowledge API,
    | or any webhook endpoint.
    |
    */
    'heartbeat_endpoint' => env('MONITOR_HEARTBEAT_ENDPOINT'),

    /*
    |--------------------------------------------------------------------------
    | Heartbeat Token
    |--------------------------------------------------------------------------
    |
    | Bearer token for authenticating heartbeat requests.
    |
    */
    'heartbeat_token' => env('MONITOR_HEARTBEAT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Alert Thresholds
    |--------------------------------------------------------------------------
    */
    'thresholds' => [
        'memory_warning' => 80,
        'memory_critical' => 90,
        'disk_warning' => 80,
        'disk_critical' => 90,
        'load_warning' => 10,
    ],
];
