<?php

return [
    'button' => 'Create ticket',
    'heading' => 'Report an issue',
    'submit' => 'Send ticket',

    'fields' => [
        'title' => 'Title',
        'description' => 'Description',
        'priority' => 'Priority',
        'page_url' => 'Page URL',
        'reporter_name' => 'Your name',
        'reporter_email' => 'Your email',
        'screenshots' => 'Screenshots',
    ],

    'priorities' => [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ],

    'help' => [
        'screenshots' => 'Up to 5 images (PNG, JPEG or WebP), 2 MB each, 3.5 MB combined.',
    ],

    'success' => [
        'title' => 'Ticket created',
        'body' => 'Your ticket number is :number.',
    ],

    'errors' => [
        'failed' => 'The ticket could not be sent',
        'validation' => 'Please check the highlighted fields.',
        'not_configured' => 'Ticket reporting is not configured. Set TMS_TICKETS_URL and TMS_TICKETS_TOKEN.',
        'unreachable' => 'The ticket system could not be reached. Please try again later.',
        'unauthorized' => 'The ticket system rejected this application token.',
        'payload_too_large' => 'The ticket is too large. Remove some screenshots and try again.',
        'rate_limited' => 'Too many tickets were sent recently. Please wait a moment.',
        'server' => 'The ticket system had an internal error. Please try again later.',
        'generic' => 'The ticket system returned an unexpected response (:status).',
        'too_many_screenshots' => 'You can attach at most :max screenshots.',
        'invalid_type' => 'Screenshots must be PNG, JPEG or WebP images.',
        'file_too_large' => 'Each screenshot must be 2 MB or smaller.',
        'total_too_large' => 'Screenshots together must be 3.5 MB or smaller.',
    ],
];
