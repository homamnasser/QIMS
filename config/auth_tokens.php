<?php

return [
    'mobile' => [
        'access_token_minutes' => max(
            1,
            (int) env('MOBILE_ACCESS_TOKEN_MINUTES', 60)
        ),
        'refresh_token_days' => max(
            1,
            (int) env('MOBILE_REFRESH_TOKEN_DAYS', 30)
        ),
        'abilities' => [
            'access' => 'mobile:access',
            'refresh' => 'mobile:refresh',
        ],
    ],
];
