<?php

return [
    'notifications' => [
        'email_enabled' => env('SAKIP_EMAIL_NOTIFICATIONS', false),
        'deadline_reminder_days' => (int) env('SAKIP_DEADLINE_REMINDER_DAYS', 7),
    ],
];
