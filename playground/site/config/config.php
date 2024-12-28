<?php

return [
    'debug' => env('KIRBY_DEBUG', false),

    'languages' => true,

    'content' => [
        'locking' => false
    ],

    'panel' => [
        'css' => array_filter([
            'assets/panel.css',
            env('DEMO', false) ? 'assets/panel-demo.css' : false
        ]),
        'favicon' => 'favicon.ico'
    ],

    'johannschopplich.content-translator' => [
        'title' => true,
        'DeepL' => [
            'apiKey' => env('DEEPL_API_KEY')
        ]
    ]
];
