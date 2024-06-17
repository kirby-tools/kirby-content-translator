<?php

use JohannSchopplich\ContentTranslator\Translator;
use JohannSchopplich\Licensing\Licenses;
use Kirby\Cms\App;
use Kirby\Exception\BadMethodCallException;

return [
    'routes' => fn (App $kirby) => [
        [
            'pattern' => '__content-translator__/translate',
            'method' => 'POST',
            'action' => function () use ($kirby) {
                $request = $kirby->request();
                $text = $request->get('text');
                $sourceLanguage = $request->get('sourceLanguage');
                $targetLanguage = $request->get('targetLanguage');

                if (!$text || !$targetLanguage) {
                    throw new BadMethodCallException('Missing parameters "text" or "targetLanguage"');
                }

                $text = Translator::translateText($text, $targetLanguage, $sourceLanguage);

                return compact('text');
            }
        ],
        [
            'pattern' => '__content-translator__/register',
            'method' => 'POST',
            'action' => function () {
                $licenses = Licenses::read('johannschopplich/kirby-content-translator', ['migrate' => false]);
                return $licenses->registerFromRequest();
            }
        ]
    ]
];
