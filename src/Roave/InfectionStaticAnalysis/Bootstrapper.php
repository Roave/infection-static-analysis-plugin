<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysis;

use Infection\Container;
use Infection\Mutant\MutantExecutionResultFactory;
use ReflectionMethod;
use Roave\InfectionStaticAnalysis\Psalm\RunStaticAnalysisAgainstMutant;

/** @internal */
final class Bootstrapper
{
    public static function bootstrap(
        Container $container,
        RunStaticAnalysisAgainstMutant $runStaticAnalysis
    ): Container {
        $reflectionOffsetSet = (new ReflectionMethod(Container::class, 'offsetSet'));

        $new_container = clone $container;
        $factory       = static function () use ($container, $runStaticAnalysis): MutantExecutionResultFactory {
            return new RunStaticAnalysisAgainstEscapedMutant(
                $container->getMutantExecutionResultFactory(),
                $runStaticAnalysis,
            );
        };

        $reflectionOffsetSet->setAccessible(true);
        $reflectionOffsetSet->invokeArgs($new_container, [MutantExecutionResultFactory::class, $factory]);

        return $new_container;
    }
}
