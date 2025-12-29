<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysisTest;

use Infection\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Roave\InfectionStaticAnalysis\Bootstrapper;
use Roave\InfectionStaticAnalysis\Psalm\RunStaticAnalysisAgainstMutant;
use Roave\InfectionStaticAnalysis\RunStaticAnalysisAgainstEscapedMutant;

#[CoversClass(Bootstrapper::class)]
#[UsesClass(RunStaticAnalysisAgainstEscapedMutant::class)]
final class BootstrapperTest extends TestCase
{
    public function testWillNotTestAnything(): void
    {
        $runStaticAnalysis = $this->createStub(RunStaticAnalysisAgainstMutant::class);
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
