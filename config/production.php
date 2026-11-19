<?php

return [
    'sensor_labels' => [
        'aoc' => 'AOC',
        'gc2030' => 'GC',
        'hs20' => 'HS',
        'uv1900' => 'UV19',
        'uv2600' => 'UV26',
        'lc2kpump' => 'PUMP',
        'lc2kdet' => 'DET',
        'lc2kcto' => 'CTO',
        'lc2kfassy' => 'FA',
        'lc2ksil' => 'SIL',
    ],
    'demo_mode' => (bool) env('DASHBOARD_DEMO_MODE', false),
    'demo_worklog_path' => resource_path('demo-worklog.json'),
    'sensor_worklog_directory' => env('SENSOR_WORKLOG_DIRECTORY', base_path('storage/app/private/sensor-worklogs')),
    'worklog_origin_url' => env('WORKLOG_ORIGIN_URL'),
    'worklog_origin_token' => env('WORKLOG_ORIGIN_TOKEN'),
    'worklog_path' => env('DET_WORKLOG_PATH', base_path('storage/app/private/det-worklog.json')),
    'worklog_url' => env('DET_WORKLOG_URL'),
    'worklog_token' => env('DET_WORKLOG_TOKEN'),
];
