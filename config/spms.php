<?php

return [
    'target_assistance' => ['enabled' => env('SPMS_TARGET_ASSISTANCE_ENABLED', true)],
    'ai' => [
        'enabled' => env('SPMS_AI_ENABLED', false),
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL'),
        'document_model' => env('OPENAI_DOCUMENT_MODEL', env('OPENAI_MODEL')),
        'statement_template' => env('SPMS_REPORT_STATEMENT_TEMPLATE'),
    ],
    'email_notifications' => env('SPMS_EMAIL_NOTIFICATIONS', false),
    'pcr' => [
        'email_reminders' => env('SPMS_PCR_EMAIL_REMINDERS', true),
        'reminder_time' => env('SPMS_PCR_REMINDER_TIME', '08:00'),
    ],
];
