<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysisTest\Psalm;

use Infection\Mutant\Mutant;
use Infection\Mutation\Mutation;
use Infection\Mutation\MutationAttributeKeys;
use Infection\PhpParser\MutatedNode;
use PackageVersions\Versions;
use PHPUnit\Framework\TestCase;
use Psalm\Config;
use Psalm\Internal\Analyzer\ProjectAnalyzer;
use Psalm\Internal\IncludeCollector;
use Psalm\Internal\Provider\FileProvider;
use Psalm\Internal\Provider\Providers;
use Psalm\Internal\RuntimeCaches;
use Psalm\Report\ReportOptions;
use Roave\InfectionStaticAnalysis\Psalm\RunStaticAnalysisAgainstMutant;

use function array_combine;
use function array_map;
use function define;
use function defined;
use function file_put_contents;
use function Later\now;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/** @covers \Roave\InfectionStaticAnalysis\Psalm\RunStaticAnalysisAgainstMutant */
final class RunStaticAnalysisAgainstMutantTest extends TestCase
{
    private const PSALM_WORKING_DIRECTORY = __DIR__ . '/../../../../..';
    private Mutant $mutantWithValidCode;
    private Mutant $mutantWithInvalidCode;
    private Mutant $mutantWithValidCodeReferencingProjectFiles;
    private Mutant $mutantWithValidCodeReferencingReflectionApi;
    private RunStaticAnalysisAgainstMutant $runStaticAnalysis;

    /** @var list<string> */
    private array $generatedMutantFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $validCode = <<<'PHP'
<?php

/**
 * @psalm-param positive-int $a 
 * @psalm-param positive-int $b 
 * @psalm-return positive-int 
 */
function add(int $a, int $b): int {
    return $a + $b;
}
PHP;

        $invalidCode = <<<'PHP'
<?php

/**
 * @psalm-param positive-int $a 
 * @psalm-param positive-int $b 
 * @psalm-return positive-int 
 */
function add(int $a, int $b): int {
    return $a - $b;
}
PHP;

        $validCodeReferencingProjectFiles = <<<'PHP'
<?php

function add(array $input): int {
    return count((new \Roave\InfectionStaticAnalysis\Stub\ArrayFilter())->makeAList($input));
}
PHP;

        $validCodeReferencingReflectionApi = <<<'PHP'
<?php

function hasMethod(object $input, string $method): bool {
    return (new ReflectionClass($input))
        ->hasMethod($method);
}
PHP;

        $declaredClassSymbol = <<<'PHP'
<?php class DeclaredClassSymbol {}
PHP;

        $validCodePath                         = tempnam(sys_get_temp_dir(), 'valid-code-');
        $invalidCodePath                       = tempnam(sys_get_temp_dir(), 'invalid-code-');
        $validCodeReferencingProjectFilesPath  = tempnam(sys_get_temp_dir(), 'valid-code-referencing-project-files-');
        $validCodeReferencingReflectionApiPath = tempnam(sys_get_temp_dir(), 'valid-code-referencing-reflection-api-');
        $declaredClassSymbolPath               = tempnam(sys_get_temp_dir(), 'declared-class-symbol-');
        $repeatedDeclaredClassSymbolPath       = tempnam(sys_get_temp_dir(), 'repeated-declared-class-symbol-');

        file_put_contents($validCodePath, $validCode);
        file_put_contents($invalidCodePath, $invalidCode);
        file_put_contents($validCodeReferencingProjectFilesPath, $validCodeReferencingProjectFiles);
        file_put_contents($validCodeReferencingReflectionApiPath, $validCodeReferencingReflectionApi);
        file_put_contents($declaredClassSymbolPath, $declaredClassSymbol);
        file_put_contents($repeatedDeclaredClassSymbolPath, $declaredClassSymbol);

        $mutationAttributes = array_combine(
            MutationAttributeKeys::ALL,
            array_map('strlen', MutationAttributeKeys::ALL),
        );

        $this->mutantWithValidCode = new Mutant(
            $validCodePath,
            new Mutation(
                'foo',
                [],
                'Plus',
                $mutationAttributes,
                '',
                MutatedNode::wrap([]),
                0,
                [],
            ),
            now($validCode),
            now(''),
            now(''),
        );

        $this->mutantWithInvalidCode = new Mutant(
            $invalidCodePath,
            new Mutation(
                'foo',
                [],
                'Plus',
                $mutationAttributes,
                '',
                MutatedNode::wrap([]),
                0,
                [],
            ),
            now($invalidCode),
            now(''),
            now(''),
        );

        $this->mutantWithValidCodeReferencingProjectFiles = new Mutant(
            $validCodeReferencingProjectFilesPath,
            new Mutation(
                'foo',
                [],
                'Plus',
                $mutationAttributes,
                '',
                MutatedNode::wrap([]),
                0,
                [],
            ),
            now($validCodeReferencingProjectFiles),
            now(''),
            now(''),
        );

        $this->mutantWithValidCodeReferencingReflectionApi = new Mutant(
            $validCodeReferencingReflectionApiPath,
            new Mutation(
                'foo',
                [],
                'Plus',
                $mutationAttributes,
                '',
                MutatedNode::wrap([]),
                0,
                [],
            ),
            now($validCodeReferencingProjectFiles),
            now(''),
            now(''),
        );

        if (! defined('PSALM_VERSION')) {
            define('PSALM_VERSION', Versions::getVersion('vimeo/psalm'));
        }

        if (! defined('PHP_PARSER_VERSION')) {
            define('PHP_PARSER_VERSION', Versions::getVersion('nikic/php-parser'));
        }

        RuntimeCaches::clearAll();

        $config = Config::getConfigForPath(
            self::PSALM_WORKING_DIRECTORY,
            self::PSALM_WORKING_DIRECTORY
        );

        $config->setIncludeCollector(new IncludeCollector());

        $this->runStaticAnalysis = new RunStaticAnalysisAgainstMutant(new ProjectAnalyzer(
            $config,
            new Providers(new FileProvider()),
            new ReportOptions(),
        ));
    }

    protected function tearDown(): void
    {
        foreach ($this->generatedMutantFiles as $mutatedFile) {
            unlink($mutatedFile);
        }

        $this->generatedMutantFiles = [];

        unlink($this->mutantWithValidCode->getFilePath());
        unlink($this->mutantWithInvalidCode->getFilePath());
        unlink($this->mutantWithValidCodeReferencingProjectFiles->getFilePath());
        unlink($this->mutantWithValidCodeReferencingReflectionApi->getFilePath());

        parent::tearDown();
    }

    public function testWillConsiderMutantValidIfNoErrorsAreDetectedByStaticAnalysis(): void
    {
        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->mutantWithValidCode));
    }

    public function testWillConsiderMutantInvalidIfErrorsAreDetectedByStaticAnalysis(): void
    {
        self::assertFalse($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->mutantWithInvalidCode));
    }

    public function testWillConsiderMutantReferencingProjectFilesAsValid(): void
    {
        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->mutantWithValidCodeReferencingProjectFiles));
    }

    public function testWillConsiderMutantReferencingReflectionApiAsValid(): void
    {
        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->mutantWithValidCodeReferencingReflectionApi));
    }

    public function testWillConsiderMutantWithRepeatedClassSymbolDeclarationAsEscaped(): void
    {
        $declaresClassSymbol   = $this->makeMutant('declares-class-symbol-', '<?php class DeclaredClassSymbol {}');
        $reDeclaresClassSymbol = $this->makeMutant('re-declares-class-symbol-', '<?php class DeclaredClassSymbol {}');

        self::assertTrue(
            $this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($declaresClassSymbol),
            'Class symbol was seen for the first time ever - no static analysis issues - mutation is legit'
        );
        self::assertTrue(
            $this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($reDeclaresClassSymbol),
            'Class symbol was seen for the second time (on a new file) - no static analysis issues - mutation is legit'
        );
    }

    private function makeMutant(
        string $pathPrefix,
        string $mutatedCode
    ): Mutant {
        $mutatedCodePath = tempnam(sys_get_temp_dir(), $pathPrefix);
        file_put_contents($mutatedCodePath, $mutatedCode);

        $this->generatedMutantFiles[] = $mutatedCodePath;

        return new Mutant(
            $mutatedCodePath,
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
            now($mutatedCode),
            now(''),
            now('')
        );
    }
}
