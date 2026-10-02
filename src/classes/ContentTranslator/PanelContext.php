<?php

declare(strict_types = 1);

namespace JohannSchopplich\ContentTranslator;

use JohannSchopplich\ContentTranslator\Translation\Strategies\CopilotAIStrategy;
use JohannSchopplich\Copilot\AI\Client as CopilotClient;
use Kirby\Cms\App;

final class PanelContext
{
    /**
     * An allowlist, because the namespace also holds closures and `Strategy`
     * instances with whatever properties their author gave them. Not the whole
     * payload: `config()` adds the server-resolved values below.
     */
    private const PANEL_OPTIONS = ['batch', 'batchConcurrency', 'confirm', 'DeepL', 'excludeFields', 'import', 'importFrom', 'includeFields', 'kirbyTags', 'slug', 'title'];

    /**
     * Builds the plugin configuration the Panel receives.
     *
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        $kirby = App::instance();
        $config = $kirby->option('johannschopplich.content-translator', []);
        $panelConfig = array_intersect_key($config, array_flip(self::PANEL_OPTIONS));

        if (isset($panelConfig['DeepL'])) {
            $panelConfig['DeepL'] = [
                'apiKey' => DeepL::hasApiKey()
            ];
        }

        $panelConfig['fieldTypes'] = TranslatorConfig::fromOptions()->fieldTypes;
        $panelConfig['strategy'] = Translator::resolveStrategyName();

        if (class_exists(CopilotClient::class)) {
            $panelConfig['ai'] = [
                'systemPrompt' => CopilotAIStrategy::resolveDefaultSystemPrompt()
            ];
        }

        return $panelConfig;
    }
}
