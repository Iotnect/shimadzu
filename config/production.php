<?php

return [
    'worklog_path' => env('DET_WORKLOG_PATH', storage_path('app/private/det-worklog.json')),
    'worklog_url' => env('DET_WORKLOG_URL'),
    'worklog_token' => env('DET_WORKLOG_TOKEN'),
];
