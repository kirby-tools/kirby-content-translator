<?php

use JohannSchopplich\ContentTranslator\KirbyText;

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
        // Drives the notifications without spending a DeepL call. The AI
        // provider goes straight to Copilot from the Panel, so none of this
        // reaches it.
        'strategy' => match (env('TRANSLATOR_STRATEGY')) {
            // A blank comes back as a failed unit, so no segment survives.
            'blank' => fn (string $text): string => '',
            // Drops the placeholders the check compares, so only the units
            // holding a KirbyTag fail.
            'partial' => fn (string $text): string => str_contains($text, '<c')
                ? (preg_replace(KirbyText::PLACEHOLDER_PATTERN, '', '[xx] ' . $text) ?? $text)
                : '[xx] ' . $text,
            // Kills one language of a batch run while the rest still land.
            'failing' => fn (string $text, string $targetLanguage): string => $targetLanguage === env('TRANSLATOR_FAILING_LANGUAGE', 'fr')
                ? throw new RuntimeException('The provider refused the request')
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
