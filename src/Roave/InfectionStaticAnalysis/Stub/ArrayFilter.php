<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysis\Stub;

use function array_values;

/** @internal this class only exists for self-testing purposes, to verify if the mutation testing plugin works */
final class ArrayFilter
{
    /**
     * @psalm-template T
     * @psalm-param array<T> $values
     * @psalm-return list<T>
     */
    public function makeAList(array $values): array
    {
        return array_values($values);
    }
}
