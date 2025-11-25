<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysis;

use Infection\Mutant\DetectionStatus;
use Infection\Mutant\MutantExecutionResult;
use Infection\Mutant\MutantExecutionResultFactory;
use Infection\Mutant\TestFrameworkMutantExecutionResultFactory;
use Infection\Process\MutantProcess;
use ReflectionProperty;
use Roave\InfectionStaticAnalysis\Psalm\RunStaticAnalysisAgainstMutant;

use function assert;
use function is_int;
use function Later\later;

/**
 * @internal
 *
 * @final not explicitly final because Infection internals don't yet type-hint
 *        against the {@see MutantExecutionResultFactory} interface
 */
class RunStaticAnalysisAgainstEscapedMutant extends TestFrameworkMutantExecutionResultFactory implements MutantExecutionResultFactory
{
    private ReflectionProperty $reflectionOriginalStartFileLocation;
    private ReflectionProperty $reflectionOriginalEndFilePosition;

    /**
     * Note: suppressions are because we are completely overriding the parent class, since `implements` is not sufficient
     *
     * @psalm-suppress ConstructorSignatureMismatch
     * @psalm-suppress ImplementedParamTypeMismatch
     * @psalm-suppress ParamNameMismatch
     * @psalm-suppress MethodSignatureMismatch
     */
    public function __construct(
        private MutantExecutionResultFactory $next,
        private RunStaticAnalysisAgainstMutant $runStaticAnalysis,
    ) {
        $this->reflectionOriginalStartFileLocation = new ReflectionProperty(MutantExecutionResult::class, 'originalStartFilePosition');
        $this->reflectionOriginalEndFilePosition   = new ReflectionProperty(MutantExecutionResult::class, 'originalEndFilePosition');
    }

    /**
     * Note: suppressions are because we are completely overriding the parent class, since `implements` is not sufficient
     *
     * @psalm-suppress MethodSignatureMismatch
     */
    public function createFromProcess(MutantProcess $mutantProcess): MutantExecutionResult
    {
        $result = $this->next->createFromProcess($mutantProcess);

        if ($result->getDetectionStatus() !== DetectionStatus::ESCAPED) {
            return $result;
        }

        if ($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($mutantProcess->getMutant())) {
            return $result;
        }

        $originalStartFilePosition = $this->reflectionOriginalStartFileLocation->getValue($result);
        $originalEndFilePosition   = $this->reflectionOriginalEndFilePosition->getValue($result);

        assert(is_int($originalStartFilePosition));
        assert(is_int($originalEndFilePosition));

        return new MutantExecutionResult(
            $result->getProcessCommandLine(),
            $result->getProcessOutput(),
            DetectionStatus::KILLED_BY_STATIC_ANALYSIS,
            later(static fn () => yield $result->getMutantDiff()),
            $result->getMutantHash(),
            $result->getMutatorClass(),
            $result->getMutatorName(),
            $result->getOriginalFilePath(),
            $result->getOriginalStartingLine(),
            $result->getOriginalEndingLine(),
            $originalStartFilePosition,
            $originalEndFilePosition,
            later(static fn () => yield $result->getOriginalCode()),
            later(static fn () => yield $result->getMutatedCode()),
            $result->getTests(),
            $result->getProcessRuntime(),
        );
    }
}
