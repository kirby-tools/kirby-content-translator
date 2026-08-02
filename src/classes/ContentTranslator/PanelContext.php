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
     * instances with whatever properties their author gave them.
     */
    private const PANEL_OPTIONS = ['batch', 'batchConcurrency', 'confirm', 'DeepL', 'excludeFields', 'fieldTypes', 'import', 'importFrom', 'includeFields', 'kirbyTags', 'slug', 'title', 'viewButton'];

    /**
     * Builds the plugin configuration the Panel receives.
     *
     * @return array<string,mixed>
     */
    public static function config(): array
    {
        $kirby = App::instance();
        $config = $kirby->option('johannschopplich.content-translator', []);
        $panelConfig = array_intersect_key($config, array_flip(self::PANEL_OPTIONS));

        if (isset($panelConfig['DeepL'])) {
            // Mirrors the check in `DeepL::__construct`, so the flag means
            // "a key DeepL would accept" rather than "a key is present"
            $apiKey = $config['DeepL']['apiKey'] ?? null;
            $panelConfig['DeepL'] = [
                'apiKey' => is_string($apiKey) && $apiKey !== ''
            ];
        }

        $panelConfig['strategy'] = Translator::resolveStrategyName();

        if (class_exists(CopilotClient::class)) {
            $panelConfig['ai'] = [
                'systemPrompt' => CopilotAIStrategy::resolveDefaultSystemPrompt()
            ];
        }

        // Keep backwards compatibility with Kirby 4
        // TODO: Deprecated, remove in Kirby 6
        $panelConfig['viewButton'] ??= true;

        return $panelConfig;
    }
}
