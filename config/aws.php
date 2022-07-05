<?php

return [

    'recognition' => [
        'region'    =>  env('AWS_DEFAULT_REGION'),
        'version'   => 'latest',
        'credentials' => [
            'key'    => env('AWS_SECRET_ACCESS_KEY'),
            'secret' => env('AWS_ACCESS_KEY_ID'),
        ]
    ],

];
