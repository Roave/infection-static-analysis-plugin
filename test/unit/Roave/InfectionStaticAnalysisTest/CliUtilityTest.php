<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysisTest;

use PHPUnit\Framework\TestCase;
use Roave\InfectionStaticAnalysis\CliUtility;
use RuntimeException;

use function sprintf;

/** @covers \Roave\InfectionStaticAnalysis\CliUtility */
final class CliUtilityTest extends TestCase
{
    /**
     * @param list<non-empty-string> $expectedNewArguments
     * @param non-empty-string|null  $expectedArgumentValue
     * @param list<non-empty-string> $arguments
     * @param non-empty-string       $argument
     *
     * @dataProvider provideExtractionData
     */
    public function testExtractArgument(
        array $expectedNewArguments,
        string|null $expectedArgumentValue,
        array $arguments,
        string $argument,
    ): void {
        $result = CliUtility::extractArgument($arguments, $argument);

        self::assertSame($expectedNewArguments, $result[0]);
        self::assertSame($expectedArgumentValue, $result[1]);
    }

    /**
     * @return list<array{
     *     0: list<non-empty-string>,
     *     1: (non-empty-string|null),
     *     2: list<non-empty-string>,
     *     3: non-empty-string
     * }>
     */
    public static function provideExtractionData(): array
    {
        return [
            [
                ['vendor/bin/roave-infection-static-analysis-plugin'],
                'configuration/psalm.xml',
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config', 'configuration/psalm.xml'],
                'psalm-config',
            ],
            [
                ['vendor/bin/roave-infection-static-analysis-plugin'],
                'configuration/psalm.xml',
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config', '"configuration/psalm.xml"'],
                'psalm-config',
            ],
            [
                ['vendor/bin/roave-infection-static-analysis-plugin'],
                'configuration/psalm.xml',
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config="configuration/psalm.xml"'],
                'psalm-config',
            ],
            [
                ['vendor/bin/roave-infection-static-analysis-plugin'],
                'configuration/psalm.xml',
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config=configuration/psalm.xml"'],
                'psalm-config',
            ],
            [
                ['vendor/bin/roave-infection-static-analysis-plugin'],
                'configuration/psalm.xml',
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config="configuration/psalm.xml'],
                'psalm-config',
            ],
            [
                ['vendor/bin/roave-infection-static-analysis-plugin'],
                'configuration/psalm.xml',
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config=configuration/psalm.xml'],
                'psalm-config',
            ],
            [
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config=foo'],
                'configuration/psalm.xml',
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config=configuration/psalm.xml', '--psalm-config=foo'],
                'psalm-config',
            ],
            [
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config=foo'],
                'configuration/psalm.xml',
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config', 'configuration/psalm.xml', '--psalm-config=foo'],
                'psalm-config',
            ],
            [
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config', 'foo'],
                'configuration/psalm.xml',
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config=configuration/psalm.xml', '--psalm-config', 'foo'],
                'psalm-config',
            ],
            [
                ['vendor/bin/roave-infection-static-analysis-plugin'],
                null,
                ['vendor/bin/roave-infection-static-analysis-plugin'],
                'psalm-config',
            ],
            [
                [],
                null,
                [],
                'psalm-config',
            ],
            [
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config', 'configuration/psalm.xml'],
                null,
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config', 'configuration/psalm.xml'],
                'psalm',
            ],
            [
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config', 'configuration/psalm.xml'],
                null,
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config', 'configuration/psalm.xml'],
                'psalm',
            ],
        ];
    }

    /**
     * @param list<non-empty-string> $arguments
     * @param non-empty-string       $argument
     *
     * @dataProvider provideExtractionMissingValueData
     */
    public function testExtractArgumentThrowsForMissingValue(
        array $arguments,
        string $argument,
    ): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Please provide a value for "%s" argument.', $argument));

        CliUtility::extractArgument($arguments, $argument);
    }

    /** @return list<array{0: list<non-empty-string>, 1: non-empty-string}> */
    public static function provideExtractionMissingValueData(): array
    {
        return [
            [
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config'],
                'psalm-config',
            ],
            [
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config='],
                'psalm-config',
            ],
            [
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config=""'],
                'psalm-config',
            ],
            [
                ['vendor/bin/roave-infection-static-analysis-plugin', '--psalm-config="'],
                'psalm-config',
            ],
        ];
    }
}
