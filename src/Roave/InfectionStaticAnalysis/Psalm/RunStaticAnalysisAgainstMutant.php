<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysis\Psalm;

use Infection\Mutant\Mutant;
use Psalm\Config;
use Psalm\Internal\Analyzer\ProjectAnalyzer;
use Psalm\Internal\Provider\FileProvider;
use Psalm\Internal\Provider\Providers;
use Psalm\Report\ReportOptions;

use function array_key_exists;

/**
 * @internal
 *
 * @final not explicitly final because we don't yet have a uniform API for this type of analysis
 */
class RunStaticAnalysisAgainstMutant
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function isMutantStillValidAccordingToStaticAnalysis(Mutant $mutant): bool
    {
        $path            = $mutant->getFilePath();
        $projectAnalyzer = new ProjectAnalyzer(
            $this->config,
            new Providers(new FileProvider()),
            new ReportOptions()
        );

        $projectAnalyzer->checkFile($path);

        return ! array_key_exists(
            $path,
            $projectAnalyzer->getCodebase()
                ->file_reference_provider
                ->getExistingIssues()
        );
    }
}
