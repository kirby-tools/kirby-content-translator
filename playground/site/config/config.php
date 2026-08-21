<?php

return [
    'debug' => env('KIRBY_DEBUG', false),

    'languages' => true,

    'content' => [
        'locking' => false
    ],

    'panel' => [
        'css' => array_filter([
            env('PLAYGROUND') !== false ? 'assets/panel-demo.css' : null
        ]),
        'favicon' => 'favicon.ico',
        'vue' => [
            'compiler' => false
        ]
    ],

    'johannschopplich.copilot' => [
        'provider' => 'google',
        'providers' => [
            'openai' => [
                'model' => 'gpt-5.6-terra',
                'apiKey' => env('OPENAI_API_KEY', 'YOUR_API_KEY')
            ],
            'google' => [
                'model' => 'gemini-3.1-pro-preview',
                'apiKey' => env('GOOGLE_API_KEY', 'YOUR_API_KEY')
            ],
            'anthropic' => [
                'apiKey' => env('ANTHROPIC_API_KEY', 'YOUR_API_KEY')
            ],
            'mistral' => [
                'model' => 'mistral-medium-latest',
                'apiKey' => env('MISTRAL_API_KEY', 'YOUR_API_KEY')
            ]
        ]
    ],

    'johannschopplich.content-translator' => [
        // Drives the notifications without spending a DeepL call. `blank`
        // makes every unit come back unusable, `partial` only the fields
        // holding a KirbyTag. The AI provider goes straight to Copilot from
        // the Panel, so this never reaches it.
        'strategy' => match (env('TRANSLATOR_STRATEGY')) {
            'blank' => fn (string $text): string => '',
            'partial' => fn (string $text): string => str_contains($text, '<c')
                ? (preg_replace('!<c\d+\s*/>!', '', '[xx] ' . $text) ?? $text)
                : '[xx] ' . $text,
            default => null
        },

        'DeepL' => [
            'apiKey' => env('DEEPL_API_KEY'),
            'targetLanguageOverrides' => [
                // Kirby language code => DeepL target code, for languages whose
                // code and locale name no code DeepL knows
                'cn' => 'ZH-HANS'
            ]
        ],
        'kirbyTags' => [
            'link' => ['text', 'title'], // Translate link text and title, but not the URL
            'image' => ['alt', 'title', 'caption'], // Translate image descriptions
            'file' => ['text', 'title'], // Translate download link text
            'email' => ['text', 'title'] // Translate email link text
        ]
    ]
];
