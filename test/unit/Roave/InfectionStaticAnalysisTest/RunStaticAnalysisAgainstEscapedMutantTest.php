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
use ReflectionProperty;
use Roave\InfectionStaticAnalysis\Psalm\RunStaticAnalysisAgainstMutant;
use Roave\InfectionStaticAnalysis\RunStaticAnalysisAgainstEscapedMutant;
use Symfony\Component\Process\Process;

use function array_combine;
use function array_map;
use function Later\now;

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
                    array_map('strlen', MutationAttributeKeys::ALL),
                ),
                '',
                MutatedNode::wrap([]),
                0,
                [],
            ),
            now('code'),
            now(''),
            now(''),
        );

        $this->process        = new MutantProcess(new Process(['echo', 'hi']), $this->mutant);
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
            now('diff'),
            'a-hash',
            'AssignmentEqual',
            '/tmp/my-file',
            1,
            10,
            2,
            8,
            now('code'),
            now('mutated code'),
            [],
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

        $this->staticAnalysis->expects(self::any())
            ->method('formatLastIssues')
            ->willReturn('formatted Psalm issues');

        $nextFactoryResult = new MutantExecutionResult(
            'echo hi',
            'formatted Psalm issues',
            DetectionStatus::ESCAPED,
            now('diff'),
            'a-hash',
            'AssignmentEqual',
            '/tmp/my-file',
            1,
            10,
            2,
            8,
            now('code'),
            now('mutated code'),
            [],
        );

        $this->nextFactory->expects(self::once())
            ->method('createFromProcess')
            ->with(self::equalTo($this->process))
            ->willReturn($nextFactoryResult);

        $result = $this->factory->createFromProcess($this->process);

        self::assertEquals($nextFactoryResult->getMutatedCode(), $result->getMutatedCode());
        self::assertEquals($nextFactoryResult->getOriginalCode(), $result->getOriginalCode());
        self::assertEquals($nextFactoryResult->getOriginalStartingLine(), $result->getOriginalStartingLine());
        self::assertEquals($nextFactoryResult->getOriginalEndingLine(), $result->getOriginalEndingLine());
        self::assertEquals($nextFactoryResult->getOriginalFilePath(), $result->getOriginalFilePath());
        self::assertEquals($nextFactoryResult->getMutatorName(), $result->getMutatorName());
        self::assertEquals($nextFactoryResult->getMutantDiff(), $result->getMutantDiff());
        self::assertEquals($nextFactoryResult->getMutantHash(), $result->getMutantHash());
        self::assertEquals($nextFactoryResult->getProcessOutput(), $result->getProcessOutput());
        self::assertEquals('Static Analysis', $result->getProcessCommandLine());
        self::assertSame(DetectionStatus::KILLED, $result->getDetectionStatus());

        $reflectionOriginalStartFileLocation = new ReflectionProperty(MutantExecutionResult::class, 'originalStartFilePosition');
        $reflectionOriginalEndFilePosition   = new ReflectionProperty(MutantExecutionResult::class, 'originalEndFilePosition');

        self::assertSame(2, $reflectionOriginalStartFileLocation->getValue($nextFactoryResult));
        self::assertSame(8, $reflectionOriginalEndFilePosition->getValue($nextFactoryResult));
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
            now('diff'),
            'a-hash',
            'AssignmentEqual',
            '/tmp/my-file',
            1,
            10,
            2,
            8,
            now('code'),
            now('mutated code'),
            [],
        );

        $this->nextFactory->expects(self::once())
            ->method('createFromProcess')
            ->with(self::equalTo($this->process))
            ->willReturn($nextFactoryResult);

        self::assertSame($nextFactoryResult, $this->factory->createFromProcess($this->process));
    }
}
