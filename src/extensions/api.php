<?php

use JohannSchopplich\ContentTranslator\Licenses;
use JohannSchopplich\ContentTranslator\Translator;
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
            'action' => function () use ($kirby) {
                $request = $kirby->request();
                $email = $request->get('email');
                $orderId = $request->get('orderId');

                if (!$email || !$orderId) {
                    throw new BadMethodCallException('Missing license registration parameters "email" or "orderId"');
                }

                $licenses = Licenses::read();
                $licenses->register($email, $orderId);

                return [
                    'ok' => true
                ];
            }
        ]
    ]
];
