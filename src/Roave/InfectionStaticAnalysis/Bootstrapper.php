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

        // @TODO we may want to make this one lazy, but for now this is OK
        $replacedService = $container->getMutantExecutionResultFactory();
        $factory         = static function () use ($replacedService, $runStaticAnalysis): MutantExecutionResultFactory {
            return new RunStaticAnalysisAgainstEscapedMutant(
                $replacedService,
                $runStaticAnalysis
            );
        };

        $reflectionOffsetSet->setAccessible(true);
        $reflectionOffsetSet->invokeArgs($container, [MutantExecutionResultFactory::class, $factory]);

        return $container;
    }
}
