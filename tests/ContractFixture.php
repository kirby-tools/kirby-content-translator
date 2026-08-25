<?php

declare(strict_types = 1);

/**
 * Reads the fixture `contract.test.ts` asserts against, so a moved file or a
 * renamed entry breaks in one place rather than in every suite that pins one.
 */
trait ContractFixture
{
    /**
     * @return array<string, mixed>
     */
    private static function contract(): array
    {
        return json_decode(file_get_contents(__DIR__ . '/fixtures/contract.json'), true);
    }
}
