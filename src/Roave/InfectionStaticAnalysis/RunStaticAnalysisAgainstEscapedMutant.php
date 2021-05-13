<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysis;

use Infection\Mutant\DetectionStatus;
use Infection\Mutant\MutantExecutionResult;
use Infection\Mutant\MutantExecutionResultFactory;
use Infection\Process\MutantProcess;
use Roave\InfectionStaticAnalysis\Psalm\RunStaticAnalysisAgainstMutant;

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

    public function __construct(
        MutantExecutionResultFactory $next,
        RunStaticAnalysisAgainstMutant $runStaticAnalysis
    ) {
        $this->next              = $next;
        $this->runStaticAnalysis = $runStaticAnalysis;
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

        return new MutantExecutionResult(
            $result->getProcessCommandLine(),
            $result->getProcessOutput(),
            DetectionStatus::KILLED, // Mutant was squished by static analysis
            later(static fn () => yield $result->getMutantDiff()),
            $result->getMutatorName(),
            $result->getOriginalFilePath(),
            $result->getOriginalStartingLine(),
            later(static fn () => yield $result->getOriginalCode()),
            later(static fn () => yield $result->getMutatedCode())
        );
    }
}
