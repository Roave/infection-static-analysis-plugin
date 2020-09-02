<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysisTest;

use Infection\Mutant\DetectionStatus;
use Infection\Mutant\Mutant;
use Infection\Mutant\MutantExecutionResult;
use Infection\Mutant\MutantExecutionResultFactory;
use Infection\Mutation\Mutation;
use Infection\Mutation\MutationAttributeKeys;
use Infection\PhpParser\MutatedNode;
use Infection\Process\MutantProcess;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Roave\InfectionStaticAnalysis\Psalm\RunStaticAnalysisAgainstMutant;
use Roave\InfectionStaticAnalysis\RunStaticAnalysisAgainstEscapedMutant;
use Symfony\Component\Process\Process;
use function array_combine;
use function array_map;

/** @covers \Roave\InfectionStaticAnalysis\RunStaticAnalysisAgainstEscapedMutant */
final class RunStaticAnalysisAgainstEscapedMutantTest extends TestCase
{
    private MutantProcess $process;
    private Mutant $mutant;
    /** @var RunStaticAnalysisAgainstMutant&MockObject */
    private RunStaticAnalysisAgainstMutant $staticAnalysis;
    /** @var MutantExecutionResultFactory&MockObject */
    private MutantExecutionResultFactory $nextFactory;
    private RunStaticAnalysisAgainstEscapedMutant $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mutant = new Mutant(
            '/tmp/my-code',
            new Mutation(
                'foo',
                [],
                'Plus',
                array_combine(
                    MutationAttributeKeys::ALL,
                    array_map('strlen', MutationAttributeKeys::ALL)
                ),
                '',
                MutatedNode::wrap([]),
                0,
                []
            ),
            'code',
            '',
            ''
        );

        $this->process = new MutantProcess(new Process(['echo', 'hi']), $this->mutant);
        $this->staticAnalysis = $this->createMock(RunStaticAnalysisAgainstMutant::class);
        $this->nextFactory    = $this->createMock(MutantExecutionResultFactory::class);
        $this->factory        = new RunStaticAnalysisAgainstEscapedMutant($this->nextFactory, $this->staticAnalysis);
    }

    public function testWillSkipValidationOnAlreadyKilledMutants(): void
    {
        $this->staticAnalysis->expects(self::never())
            ->method('isMutantStillValidAccordingToStaticAnalysis');

        $nextFactoryResult = new MutantExecutionResult(
            'echo hi',
            'output',
            DetectionStatus::KILLED,
            'diff',
            'AssignmentEqual',
            '/tmp/my-file',
            1,
            'code',
            'mutated code'
        );

        $this->nextFactory->expects(self::once())
            ->method('createFromProcess')
            ->with(self::equalTo($this->process))
            ->willReturn($nextFactoryResult);

        $result = $this->factory->createFromProcess($this->process);

        self::assertEquals($nextFactoryResult, $result);
        self::assertSame(DetectionStatus::KILLED, $result->getDetectionStatus());
    }

    public function testWillKillMutantsThatEscapedAndFailedStaticAnalysis(): void
    {
        $this->staticAnalysis->expects(self::once())
            ->method('isMutantStillValidAccordingToStaticAnalysis')
            ->willReturn(false);

        $nextFactoryResult = new MutantExecutionResult(
            'echo hi',
            'output',
            DetectionStatus::ESCAPED,
            'diff',
            'AssignmentEqual',
            '/tmp/my-file',
            1,
            'code',
            'mutated code'
        );

        $this->nextFactory->expects(self::once())
            ->method('createFromProcess')
            ->with(self::equalTo($this->process))
            ->willReturn($nextFactoryResult);

        $result = $this->factory->createFromProcess($this->process);

        self::assertEquals($nextFactoryResult->getMutatedCode(), $result->getMutatedCode());
        self::assertEquals($nextFactoryResult->getOriginalCode(), $result->getOriginalCode());
        self::assertEquals($nextFactoryResult->getOriginalStartingLine(), $result->getOriginalStartingLine());
        self::assertEquals($nextFactoryResult->getOriginalFilePath(), $result->getOriginalFilePath());
        self::assertEquals($nextFactoryResult->getMutatorName(), $result->getMutatorName());
        self::assertEquals($nextFactoryResult->getMutantDiff(), $result->getMutantDiff());
        self::assertEquals($nextFactoryResult->getProcessOutput(), $result->getProcessOutput());
        self::assertEquals($nextFactoryResult->getProcessCommandLine(), $result->getProcessCommandLine());
        self::assertSame(DetectionStatus::KILLED, $result->getDetectionStatus());
    }

    public function testWillNotKillMutantsThatEscapedAndPassedStaticAnalysis(): void
    {
        $this->staticAnalysis->expects(self::once())
            ->method('isMutantStillValidAccordingToStaticAnalysis')
            ->willReturn(true);

        $nextFactoryResult = new MutantExecutionResult(
            'echo hi',
            'output',
            DetectionStatus::ESCAPED,
            'diff',
            'AssignmentEqual',
            '/tmp/my-file',
            1,
            'code',
            'mutated code'
        );

        $this->nextFactory->expects(self::once())
            ->method('createFromProcess')
            ->with(self::equalTo($this->process))
            ->willReturn($nextFactoryResult);

        self::assertSame($nextFactoryResult, $this->factory->createFromProcess($this->process));
    }
}
