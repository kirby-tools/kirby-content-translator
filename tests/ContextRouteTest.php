<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ContextRouteTest extends ApiRouteTestCase
{
    private function callContextRoute(array $options): mixed
    {
        return $this->callRoute(
            new App(['options' => ['johannschopplich.content-translator' => $options]]),
            '__content-translator__/context'
        );
    }

    #[Test]
    public function sends_the_panel_context_rather_than_the_raw_option_tree(): void
    {
        $response = $this->callContextRoute([
            'DeepL' => ['apiKey' => 'deepl-secret'],
            'someFutureOption' => 'test-secret'
        ]);

        $this->assertSame(['apiKey' => true], $response['config']['DeepL']);
        $this->assertArrayNotHasKey('someFutureOption', $response['config']);

        // Any Panel user of any role can read this response, so the guard
        // covers the whole envelope rather than the `config` key alone
        $payload = json_encode($response);

        $this->assertStringNotContainsString('deepl-secret', $payload);
        $this->assertStringNotContainsString('test-secret', $payload);
    }
}
