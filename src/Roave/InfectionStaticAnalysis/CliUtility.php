<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysis;

use function array_values;
use function strpos;
use function substr;

/**
 * @internal
 */
final class CliUtility
{
    private function __construct()
    {
    }

    /**
     * @return list<non-empty-string>
     */
    public static function getArguments(): array
    {
        /** @var list<non-empty-string> */
        return $_SERVER['argv'] ?? [];
    }

    /**
     * @param list<non-empty-string> $arguments
     * @param non-empty-string       $argument
     *
     * @return array{0: list<non-empty-string>, 1: (non-empty-string|null)}
     */
    public static function extractArgument(array $arguments, string $argument): array
    {
        $lookup = '--' . $argument;

        $result = null;
        foreach ($arguments as $index => $arg) {
            if ($arg === $lookup) {
                unset($arguments[$index]);
                // grab the next argument in the list
                $value = $arguments[$index + 1] ?? null;
                // if the argument is not a flag/argument name ( starts with - )
                if ($value !== null && strpos($value, '-') !== 0) {
                    // consider it the value, and remove it from the list.
                    $result = $value;
                    unset($arguments[$index + 1]);
                }

                break;
            }

            // if the argument starts with `--argument-name=`
            // we consider anything after '=' to be the value
            if (strpos($arg, $lookup . '=') === 0) {
                unset($arguments[$index]);

                $result = substr($arg, 15);
                break;
            }
        }

        $arguments = array_values($arguments);

        return [
            $arguments,
            self::removeSurroundingQuites($result),
        ];
    }

    /**
     * @return non-empty-string|null
     */
    private static function removeSurroundingQuites(?string $argument): ?string
    {
        if ($argument === null || $argument === '') {
            return null;
        }

        if ($argument[0] === '"') {
            $argument = substr($argument, 1);
        }

        if (substr($argument, -1)  === '"') {
            $argument = substr($argument, 0, -1);
        }

        return $argument === '' ? null : $argument;
    }
}
