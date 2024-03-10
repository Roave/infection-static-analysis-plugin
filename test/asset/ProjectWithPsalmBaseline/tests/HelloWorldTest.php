<?php

use Acme\ProjectWithPsalmBaseline\HelloWorld;

/**
 * @covers \Acme\ProjectWithPsalmBaseline\HelloWorld
 */
class HelloWorldTest extends \PHPUnit\Framework\TestCase
{
    public function testNothingButProvideCoverage(): void
    {
        $sut = new HelloWorld();
        $_resultWeDontCheck = $sut->takesImplictMixedReturnsIntAndIsCoveredByBaseline(3);

        $this->assertSame(4, 2+2);
    }
}