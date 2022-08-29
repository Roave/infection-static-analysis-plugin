<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysis\Psalm;

use Infection\Mutant\Mutant;
use Psalm\Internal\Analyzer\ProjectAnalyzer;

use function array_key_exists;

/**
 * @internal
 *
 * @final not explicitly final because we don't yet have a uniform API for this type of analysis
 */
class RunStaticAnalysisAgainstMutant
{
    /** @var callable(): ProjectAnalyzer */
    private $makeFreshAnalyzer;

    /**
     * Psalm has a lot of state in it, so we need a "fresh psalm" at each analysis - that's why
     * a callable factory is injected
     *
     * @see https://github.com/vimeo/psalm/issues/4117
     *
     * @param callable(): ProjectAnalyzer $makeFreshAnalyzer
     */
    public function __construct(callable $makeFreshAnalyzer)
    {
        $this->makeFreshAnalyzer = $makeFreshAnalyzer;
    }

    public function isMutantStillValidAccordingToStaticAnalysis(Mutant $mutant): bool
    {
        $path            = $mutant->getFilePath();
        $projectAnalyzer = ($this->makeFreshAnalyzer)();

        $projectAnalyzer->checkFile($path);

        return ! array_key_exists(
            $path,
            $projectAnalyzer->getCodebase()
                ->file_reference_provider
                ->getExistingIssues(),
        );
    }
}
