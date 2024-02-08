<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysisTest;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ProjectWithBaselineTest extends TestCase
{
    public function testItSpuriouslyReportsMutantAsKilledWithPsalmBaseline(): void
    {
        $process = new Process([__DIR__ . '/../../../../bin/roave-infection-static-analysis-plugin']);
        $process->setWorkingDirectory(__DIR__ . '/../../../asset/ProjectWithPsalmBaseline');

        $process->mustRun();

        $output = $process->getOutput();

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('4 mutants were killed', $output);
        $this->assertStringContainsString('Mutation Score Indicator (MSI): 100%', $output);
    }
}
