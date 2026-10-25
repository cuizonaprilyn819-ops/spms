<?php

return [
    'ai' => [
        'enabled' => env('SPMS_AI_ENABLED', false),
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL'),
    ],
    'email_notifications' => env('SPMS_EMAIL_NOTIFICATIONS', false),
];
