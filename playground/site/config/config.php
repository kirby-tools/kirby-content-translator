<?php

return [
    'debug' => env('KIRBY_DEBUG', false),

    'languages' => true,

    'content' => [
        'locking' => false
    ],

    'panel' => [
        'css' => env('DEMO', false) ? 'assets/panel.css' : null,
        'favicon' => 'favicon.ico'
    ],

    'johannschopplich.content-translator' => [
        'DeepL' => [
            'apiKey' => env('DEEPL_API_KEY')
        ]
    ]
];
