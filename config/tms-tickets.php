<?php

return [

    /*
    | Turn the "Create ticket" button on or off without removing the plugin.
    */
    'enabled' => env('TMS_TICKETS_ENABLED', true),

    /*
    | Base URL of the TMS, e.g. https://tms.example.com (no trailing path).
    */
    'url' => env('TMS_TICKETS_URL'),

    /*
    | SourceApp token issued from the TMS admin (one per host project).
    */
    'token' => env('TMS_TICKETS_TOKEN'),

    /*
    | Seconds to wait for the TMS before showing a failure message.
    */
    'timeout' => (int) env('TMS_TICKETS_TIMEOUT', 10),

];
