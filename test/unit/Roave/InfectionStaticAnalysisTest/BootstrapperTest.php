<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysisTest;

use Infection\Container;
use PHPUnit\Framework\TestCase;
use Roave\InfectionStaticAnalysis\Bootstrapper;
use Roave\InfectionStaticAnalysis\Psalm\RunStaticAnalysisAgainstMutant;
use Roave\InfectionStaticAnalysis\RunStaticAnalysisAgainstEscapedMutant;

/**
 * @uses \Roave\InfectionStaticAnalysis\RunStaticAnalysisAgainstEscapedMutant
 *
 * @covers \Roave\InfectionStaticAnalysis\Bootstrapper
 */
final class BootstrapperTest extends TestCase
{
    public function testWillNotTestAnything(): void
    {
        $runStaticAnalysis = $this->createMock(RunStaticAnalysisAgainstMutant::class);
        Bootstrapper::bootstrap(
            Container::create(),
            $runStaticAnalysis,
        );

        self::assertInstanceOf(
            RunStaticAnalysisAgainstEscapedMutant::class,
            Bootstrapper::bootstrap(
                Container::create(),
                $runStaticAnalysis,
            )->getMutantExecutionResultFactory(),
        );
    }
}
