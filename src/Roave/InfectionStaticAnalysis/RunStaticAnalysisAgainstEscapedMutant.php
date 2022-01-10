<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysis;

use Infection\Mutant\DetectionStatus;
use Infection\Mutant\MutantExecutionResult;
use Infection\Mutant\MutantExecutionResultFactory;
use Infection\Process\MutantProcess;
use ReflectionProperty;
use Roave\InfectionStaticAnalysis\Psalm\RunStaticAnalysisAgainstMutant;

use function assert;
use function is_int;
use function Later\later;

/**
 * @internal
 *
 * @final not explicitly final because there is no interface for {@see MutantExecutionResultFactory}
 */
class RunStaticAnalysisAgainstEscapedMutant extends MutantExecutionResultFactory
{
    private MutantExecutionResultFactory $next;
    private RunStaticAnalysisAgainstMutant $runStaticAnalysis;
    private ReflectionProperty $reflectionOriginalStartFileLocation;
    private ReflectionProperty $reflectionOriginalEndFilePosition;

    public function __construct(
        MutantExecutionResultFactory $next,
        RunStaticAnalysisAgainstMutant $runStaticAnalysis
    ) {
        $this->next              = $next;
        $this->runStaticAnalysis = $runStaticAnalysis;

        $this->reflectionOriginalStartFileLocation = new ReflectionProperty(MutantExecutionResult::class, 'originalStartFilePosition');
        $this->reflectionOriginalEndFilePosition   = new ReflectionProperty(MutantExecutionResult::class, 'originalEndFilePosition');

        $this->reflectionOriginalStartFileLocation->setAccessible(true);
        $this->reflectionOriginalEndFilePosition->setAccessible(true);
    }

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
            DetectionStatus::KILLED, // Mutant was squished by static analysis
            later(static fn () => yield $result->getMutantDiff()),
            $result->getMutantHash(),
            $result->getMutatorName(),
            $result->getOriginalFilePath(),
            $result->getOriginalStartingLine(),
            $result->getOriginalEndingLine(),
            $originalStartFilePosition,
            $originalEndFilePosition,
            later(static fn () => yield $result->getOriginalCode()),
            later(static fn () => yield $result->getMutatedCode()),
            $result->getTests()
        );
    }
}
