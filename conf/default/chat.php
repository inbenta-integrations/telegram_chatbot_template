<?php

// Inbenta Hyperchat configuration
return array(
    'chat' => array(
        'enabled' => false,
        'version' => '1',
        'appId' => '',
        'secret' => '',
        'roomId' => 1,             // Numeric value, no string (without quotes)
        'lang' => '',
        'source' => 3,             // Numeric value, no string (without quotes)
        'guestName' => '',
        'guestContact' => '',
        'server' => '<server>',    // Your HyperChat server URL (ask your contact person at Inbenta)
        'server_port' => 443,
        'queue' => [
            'active' => true
        ]
    ),
    'triesBeforeEscalation' => 0,
    'negativeRatingsBeforeEscalation' => 0
);
