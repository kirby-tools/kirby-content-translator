<?php

use JohannSchopplich\ContentTranslator\TranslationCoverage;
use Kirby\Cms\App;
use Kirby\Cms\Event;
use Kirby\Cms\Page;
use Kirby\Uuid\PageUuid;

return [
    'page.*:after' => function (Event $event) {
        if ($event->action() === 'render') {
            return;
        }

        $cache = App::instance()->cache('johannschopplich.content-translator');
        $cache->remove('treeIndex');

        foreach ($event->arguments() as $argument) {
            if ($argument instanceof Page) {
                if ($uuid = PageUuid::retrieveId($argument)) {
                    $cache->remove(TranslationCoverage::PAGE_COVERAGE_CACHE_PREFIX . $uuid);
                }
                $cache->remove(TranslationCoverage::PAGE_COVERAGE_CACHE_PREFIX . $argument->id());
            }
        }
    },
    'site.update:after' => function () {
        App::instance()->cache('johannschopplich.content-translator')->remove('treeIndex');
    },
    'language.*:after' => function () {
        App::instance()->cache('johannschopplich.content-translator')->flush();
    },
];
