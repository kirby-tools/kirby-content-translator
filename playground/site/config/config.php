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
        'favicon' => 'favicon.ico',
        'vue' => [
            'compiler' => false
        ]
    ],

    'johannschopplich.content-translator' => [
        'DeepL' => [
            'apiKey' => env('DEEPL_API_KEY')
        ],
        'kirbyTags' => [
            'link' => ['text', 'title'], // Translate link text and title, but not the URL
            'image' => ['alt', 'title', 'caption'], // Translate image descriptions
            'file' => ['text', 'title'], // Translate download link text
            'email' => ['text', 'title'] // Translate email link text
        ]
    ]
];
