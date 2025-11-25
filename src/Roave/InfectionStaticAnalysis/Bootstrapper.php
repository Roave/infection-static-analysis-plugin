<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysis;

use Infection\Container;
use Infection\Mutant\MutantExecutionResultFactory;
use Infection\Mutant\TestFrameworkMutantExecutionResultFactory;
use ReflectionMethod;
use Roave\InfectionStaticAnalysis\Psalm\RunStaticAnalysisAgainstMutant;

/** @internal */
final class Bootstrapper
{
    public static function bootstrap(
        Container $container,
        RunStaticAnalysisAgainstMutant $runStaticAnalysis,
    ): Container {
        $reflectionOffsetSet = new ReflectionMethod(Container::class, 'offsetSet');

        $factory = static function (Container $container) use ($runStaticAnalysis): MutantExecutionResultFactory {
            return new RunStaticAnalysisAgainstEscapedMutant(
                new TestFrameworkMutantExecutionResultFactory($container->getTestFrameworkAdapter()),
                $runStaticAnalysis,
            );
        };

        $reflectionOffsetSet->invokeArgs($container, [TestFrameworkMutantExecutionResultFactory::class, $factory]);

        return $container;
    }
}
