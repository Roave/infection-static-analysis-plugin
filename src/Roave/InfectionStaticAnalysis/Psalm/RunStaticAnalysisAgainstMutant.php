<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysis\Psalm;

use Infection\Mutant\Mutant;
use Psalm\Internal\Analyzer\IssueData;
use Psalm\Internal\Analyzer\ProjectAnalyzer;

use function array_map;
use function count;
use function implode;

/**
 * @internal
 * @psalm-suppress InternalProperty - we use Psalm's internal IssueData class here. Afaik the only other way to
 * display details of the issues would be to use one of the subclasses of \Psalm\Report, but I think none are exactly
 * what we want. Probably we can accept the risk of Psalm's internals changing and breaking this.
 *
 * @final not explicitly final because we don't yet have a uniform API for this type of analysis
 */
class RunStaticAnalysisAgainstMutant
{
    private bool $alreadyVisitedStubs = false;

    /** @var IssueData[] */
    private array $psalmIssuesFromLastMutant = [];

    public function __construct(private ProjectAnalyzer $projectAnalyzer)
    {
    }

    public function isMutantStillValidAccordingToStaticAnalysis(Mutant $mutant): bool
    {
        $this->psalmIssuesFromLastMutant = [];

        $path     = $mutant->getFilePath();
        $paths    = [$mutant->getFilePath()];
        $codebase = $this->projectAnalyzer->getCodebase();

        $codebase->invalidateInformationForFile(
            $mutant->getMutation()
                ->getOriginalFilePath(),
        );

        $codebase->addFilesToAnalyze([$path => $path]);
        $codebase->scanFiles();

        if (! $this->alreadyVisitedStubs) {
            $codebase->config->visitPreloadedStubFiles($codebase);
            $codebase->config->visitStubFiles($codebase);
            $codebase->config->visitComposerAutoloadFiles($this->projectAnalyzer);

            $this->alreadyVisitedStubs = true;
        }

        $codebase->reloadFiles($this->projectAnalyzer, $paths);
        $codebase->analyzer->analyzeFiles($this->projectAnalyzer, count($paths), false);

        $this->psalmIssuesFromLastMutant = $codebase->file_reference_provider->getExistingIssues()[$path] ?? [];

        $codebase->invalidateInformationForFile($path);

        return $this->psalmIssuesFromLastMutant === [];
    }

    public function formatLastIssues(): string
    {
        return implode(
            "\n\n",
            array_map(static fn (IssueData $issueData) => ($issueData->type . ': ' . $issueData->message .  "\n{$issueData->file_name}:{$issueData->line_from}"), $this->psalmIssuesFromLastMutant),
        );
    }
}
