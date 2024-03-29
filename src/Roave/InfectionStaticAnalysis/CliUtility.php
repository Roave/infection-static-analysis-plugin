<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysis;

use RuntimeException;

use function array_values;
use function sprintf;
use function str_starts_with;
use function substr;

/** @internal */
final class CliUtility
{
    private function __construct()
    {
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

        $result  = null;
        $present = false;
        foreach ($arguments as $index => $arg) {
            if ($arg === $lookup) {
                $present = true;
                unset($arguments[$index]);
                // grab the next argument in the list
                $value = $arguments[$index + 1] ?? null;
                // if the argument is not a flag/argument name ( starts with - )
                if ($value !== null && ! str_starts_with($value, '-')) {
                    // consider it the value, and remove it from the list.
                    $result = $value;
                    unset($arguments[$index + 1]);
                }

                break;
            }

            // if the argument starts with `--argument-name=`
            // we consider anything after '=' to be the value
            if (str_starts_with($arg, $lookup . '=')) {
                $present = true;
                unset($arguments[$index]);

                $result = substr($arg, 15);
                break;
            }
        }

        $arguments = array_values($arguments);
        $value     = self::removeSurroundingQuites($result);
        if ($present && $value === null) {
            throw new RuntimeException(sprintf('Please provide a value for "%s" argument.', $argument));
        }

        return [$arguments, $value];
    }

    /** @return non-empty-string|null */
    private static function removeSurroundingQuites(string|null $argument): string|null
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
