<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysisTest\Stub;

use PHPUnit\Framework\TestCase;
use Roave\InfectionStaticAnalysis\Stub\ArrayFilter;

/** @covers \Roave\InfectionStaticAnalysis\Stub\ArrayFilter */
final class ArrayFilterTest extends TestCase
{
    public function testListContentsArePreserved(): void
    {
        $list = (new ArrayFilter())->makeAList(['a' => 'b', 'c' => 'd']);

        self::assertCount(2, $list);
        self::assertContains('b', $list);
        self::assertContains('d', $list);
    }
}
