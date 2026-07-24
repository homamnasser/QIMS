<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Student self number
    |--------------------------------------------------------------------------
    |
    | The sequence is stored per mosque and incremented while the mosque row
    | is locked. Increasing this value later does not invalidate old numbers.
    |
    */
    'selfnumber_sequence_length' => (int) env('SURVEY_SELFNUMBER_SEQUENCE_LENGTH', 6),

    'student_access_token_minutes' => (int) env('SURVEY_STUDENT_TOKEN_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Survey schedule timezone
    |--------------------------------------------------------------------------
    |
    | Datetime inputs without an explicit offset are interpreted in this
    | business timezone, then normalized to UTC before storage.
    |
    */
    'schedule_timezone' => env('SURVEY_SCHEDULE_TIMEZONE', 'Asia/Damascus'),

    'frontend_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/'),

    'question_types' => [
        'radio',
        'checkbox',
        'short_text',
        'paragraph',
        'file',
        'date',
        'time',
    ],

    'rule_actions' => [
        'show_if',
        'hide_if',
        'require_if',
    ],
];
