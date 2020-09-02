<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysis\Psalm;

use Infection\Mutant\Mutant;
use Psalm\Config;
use Psalm\Internal\Analyzer\ProjectAnalyzer;
use Psalm\Internal\Provider\FileProvider;
use Psalm\Internal\Provider\Providers;
use Psalm\Report\ReportOptions;

/**
 * @internal
 * @final not explicitly final because we don't yet have a uniform API for this type of analysis
 *
 * @TODO careful: need to check how stateful this code really is
 */
class RunStaticAnalysisAgainstMutant
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /** @return bool whether the mutation is considered valid by static analysis */
    public function __invoke(
        Mutant $mutant
    ): bool {
        $path = $mutant->getFilePath();
        $projectAnalyzer = new ProjectAnalyzer(
            $this->config,
            new Providers(new FileProvider()),
            new ReportOptions()
        );

        $this->config->visitComposerAutoloadFiles($projectAnalyzer);
        $projectAnalyzer->checkFile($path);

        return ! count(
            $projectAnalyzer->getCodebase()
                ->file_reference_provider
                ->getExistingIssues()[$path] ?? []
        );
    }
}
