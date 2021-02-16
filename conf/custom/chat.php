<?php

// Inbenta Hyperchat configuration
return [
    'chat' => [
        'enabled' => true,
        'version' => '1',
        'appId' => '',
        'secret' => '',
        'roomId' => 1,             // Numeric value, no string (without quotes)
        'lang' => 'en',
        'source' => 3,             // Numeric value, no string (without quotes)
        'guestName' => '',
        'guestContact' => '',
        'regionServer' => 'us',     // Region of the Hyperchat server URL
        'server' => '<server>',    // Your HyperChat server URL (ask your contact person at Inbenta)
        'server_port' => 443,
        'surveyId' => '1',
        'queue' => [
            'active' => false //true
        ]
    ],
    'triesBeforeEscalation' => 2,
    'negativeRatingsBeforeEscalation' => 0
];
