<?php

declare(strict_types=1);

namespace Roave\InfectionStaticAnalysisTest\Psalm;

use Composer\InstalledVersions;
use Infection\Mutant\Mutant;
use Infection\Mutation\Mutation;
use Infection\Mutation\MutationAttributeKeys;
use Infection\PhpParser\MutatedNode;
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
use function assert;
use function copy;
use function define;
use function defined;
use function file_put_contents;
use function is_string;
use function Later\now;
use function mkdir;
use function realpath;
use function rmdir;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/** @covers \Roave\InfectionStaticAnalysis\Psalm\RunStaticAnalysisAgainstMutant */
final class RunStaticAnalysisAgainstMutantTest extends TestCase
{
    private const PSALM_WORKING_DIRECTORY = __DIR__ . '/../../../../..';
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

        if (! defined('PSALM_VERSION')) {
            define('PSALM_VERSION', InstalledVersions::getVersion('vimeo/psalm'));
        }

        if (! defined('PHP_PARSER_VERSION')) {
            define('PHP_PARSER_VERSION', InstalledVersions::getVersion('nikic/php-parser'));
        }

        RuntimeCaches::clearAll();

        $config = Config::getConfigForPath(
            self::PSALM_WORKING_DIRECTORY,
            self::PSALM_WORKING_DIRECTORY,
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

        parent::tearDown();
    }

    public function testWillConsiderMutantValidIfNoErrorsAreDetectedByStaticAnalysis(): void
    {
        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->makeMutant(
            'valid-mutated-code-',
            <<<'PHP'
<?php

/**
 * @psalm-param positive-int $a 
 * @psalm-param positive-int $b 
 * @psalm-return positive-int 
 */
function add(int $a, int $b): int {
    return $a + $b;
}
PHP,
        )));
    }

    public function testWillConsiderMutantInvalidIfErrorsAreDetectedByStaticAnalysis(): void
    {
        self::assertFalse($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->makeMutant(
            'invalid-mutated-code-',
            <<<'PHP'
<?php

/**
 * @psalm-param positive-int $a 
 * @psalm-param positive-int $b 
 * @psalm-return positive-int 
 */
function add(int $a, int $b): int {
    return $a - $b;
}
PHP,
        )));
    }

    public function testWillConsiderMutantReferencingProjectFilesAsValid(): void
    {
        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->makeMutant(
            'valid-code-referencing-project-files-',
            <<<'PHP'
<?php

function add(array $input): int {
    return count((new \Roave\InfectionStaticAnalysis\Stub\ArrayFilter())->makeAList($input));
}
PHP,
        )));
    }

    public function testWillConsiderMutantReferencingReflectionApiAsValid(): void
    {
        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->makeMutant(
            'valid-code-referencing-reflection-api-',
            <<<'PHP'
<?php

function hasMethod(object $input, string $method): bool {
    return (new ReflectionClass($input))
        ->hasMethod($method);
}
PHP,
        )));
    }

    public function testWillConsiderMutantWithRepeatedClassSymbolDeclarationAsEscaped(): void
    {
        $declaresClassSymbol   = $this->makeMutant('declares-class-symbol-', '<?php class DeclaredClassSymbol {}');
        $reDeclaresClassSymbol = $this->makeMutant('re-declares-class-symbol-', '<?php class DeclaredClassSymbol {}');

        self::assertTrue(
            $this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($declaresClassSymbol),
            'Class symbol was seen for the first time ever - no static analysis issues - mutation is legit',
        );
        self::assertTrue(
            $this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($reDeclaresClassSymbol),
            'Class symbol was seen for the second time (on a new file) - no static analysis issues - mutation is legit',
        );
    }

    public function testWillConsiderMutantOfAlreadyReferencedScannedClassAsValid(): void
    {
        $usesSourceDeclaredClassSymbol       = $this->makeMutant(
            'uses-source-declared-class-symbol-',
            <<<'PHP'
<?php

function makeArrayFilter(): array {
    return (new \Roave\InfectionStaticAnalysis\Stub\ArrayFilter())->makeAList(['a' => 'b']);
}
PHP,
        );
        $reDeclaresSourceDeclaredClassSymbol = $this->makeMutant(
            're-declares-source-declared-class-symbol-',
            <<<'PHP'
<?php

namespace Roave\InfectionStaticAnalysis\Stub;

final class ArrayFilter {
    function makeAList(): int { return 1; }
}
PHP,
            realpath(__DIR__ . '/../../../../../src/Roave/InfectionStaticAnalysis/Stub/ArrayFilter.php'),
        );

        self::assertTrue(
            $this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($usesSourceDeclaredClassSymbol),
            'Class symbol was seen for the first time in sources, through indirect scan',
        );
        self::assertTrue(
            $this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($reDeclaresSourceDeclaredClassSymbol),
            'Class symbol re-declared by the mutant, which is an altered version of it',
        );
    }

    /** @see https://github.com/vimeo/psalm/issues/5764#issuecomment-841174672 */
    public function testInternalPhpEngineConstantsCanBeReferencedFromAnalyzedMutantCode(): void
    {
        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->makeMutant(
            'reference-to-php-internal-constant-',
            '<?php return json_encode("foo", JSON_THROW_ON_ERROR);',
        )));
    }

    /** @see https://github.com/vimeo/psalm/issues/5764#issuecomment-841174672 */
    public function testInternalPhpEngineSymbolPurityPropertiesCanBeReliedUponDuringMutantAnalysis(): void
    {
        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->makeMutant(
            'usage-of-php-internal-class-purity-properties-',
            <<<'PHP'
<?php
/** @psalm-pure */
function pureTimestamp(\DateTimeImmutable $d): int {
    return $d->getTimestamp();
}
PHP,
        )));
    }

    /** @see https://github.com/vimeo/psalm/issues/5764#issuecomment-843249180 */
    public function testReferencesToInternalStaticMethodsAreConsidered(): void
    {
        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->makeMutant(
            'usage-of-php-internal-class-static-method-',
            <<<'PHP'
<?php
/** @psalm-mutation-free */
function makeDate(): ?\DateTimeInterface {
    return \DateTimeImmutable::createFromFormat('Y-m-d', '2020-01-01') ?: null;
}
PHP,
        )));
    }

    /** @see https://github.com/vimeo/psalm/issues/5764#issuecomment-843436051 */
    public function testCanReferenceInternalStubInformationAfterScanningMutantWithNoCoreClassReferences(): void
    {
        $mutantWithNoPhpCoreReferences = $this->makeMutant(
            'mutant-with-no-php-core-references-',
            '<?php return 1;',
        );
        $mutantWithPhpCoreReferences   = $this->makeMutant(
            'mutant-with-php-core-references-',
            '<?php return DateTimeImmutable::createFromFormat("Y-m-d", "2021-05-18");',
        );

        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($mutantWithNoPhpCoreReferences));
        self::assertTrue($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($mutantWithPhpCoreReferences));
    }

    /** @see https://github.com/vimeo/psalm/issues/5764#issuecomment-842687795 */
    public function testPreloadedStubsAreNotConsideredIfNotConfigured(): void
    {
        self::assertFalse($this->runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->makeMutant(
            'usage-of-unknown-preloaded-stub-class-',
            <<<'PHP'
<?php 
class StubImplementation implements \Roave\InfectionStaticAnalysisAsset\PreloadClassStub\Stub {}
PHP,
        )));
    }

    /** @see https://github.com/vimeo/psalm/issues/5764#issuecomment-842687795 */
    public function testConfiguredPreloadedStubsAreConsidered(): void
    {
        $config = Config::getConfigForPath(
            __DIR__ . '/../../../../asset/PreloadClassStub',
            __DIR__ . '/../../../../asset/PreloadClassStub',
        );

        $config->setIncludeCollector(new IncludeCollector());

        $runStaticAnalysis = new RunStaticAnalysisAgainstMutant(new ProjectAnalyzer(
            $config,
            new Providers(new FileProvider()),
            new ReportOptions(),
        ));

        self::assertTrue($runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($this->makeMutant(
            'usage-of-preloaded-stub-class-',
            <<<'PHP'
<?php 
class StubImplementation implements \Roave\InfectionStaticAnalysisAsset\PreloadClassStub\Stub {}
PHP,
        )));
    }

    /** @see https://github.com/vimeo/psalm/issues/5764#issuecomment-842687795 */
    public function testStubPreloadingHappensOnlyOnce(): void
    {
        $mutableProject = tempnam(sys_get_temp_dir(), 'mutable-project-stub-');

        unlink($mutableProject);
        mkdir($mutableProject);
        mkdir($mutableProject . '/vendor');
        mkdir($mutableProject . '/vendor/composer');

        copy(__DIR__ . '/../../../../asset/MutableProjectStub/psalm.xml', $mutableProject . '/psalm.xml');

        $config = Config::getConfigForPath($mutableProject, $mutableProject);

        $config->setIncludeCollector(new IncludeCollector());

        $runStaticAnalysis = new RunStaticAnalysisAgainstMutant(new ProjectAnalyzer(
            $config,
            new Providers(new FileProvider()),
            new ReportOptions(),
        ));

        $mutant = $this->makeMutant('usage-of-mutable-stub-class-', '<?php echo "hello";');

        self::assertTrue($runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($mutant));

        // Modifying the project definitions that are stored on disk: this modified version should not
        // be considered by psalm after the first analysis.
        file_put_contents(
            $mutableProject . '/vendor/composer/autoload_files.php',
            '<?php throw new \Exception("Psalm should not be scanning this file location again");',
        );

        self::assertTrue($runStaticAnalysis->isMutantStillValidAccordingToStaticAnalysis($mutant));

        unlink($mutableProject . '/vendor/composer/autoload_files.php');
        unlink($mutableProject . '/psalm.xml');
        rmdir($mutableProject . '/vendor/composer');
        rmdir($mutableProject . '/vendor');
        rmdir($mutableProject);
    }

    private function makeMutant(
        string $pathPrefix,
        string $mutatedCode,
        string $originalFilePath = 'irrelevant',
    ): Mutant {
        $mutatedCodePath = tempnam(sys_get_temp_dir(), $pathPrefix);
        assert(is_string($mutatedCodePath));
        file_put_contents($mutatedCodePath, $mutatedCode);

        $this->generatedMutantFiles[] = $mutatedCodePath;

        return new Mutant(
            $mutatedCodePath,
            new Mutation(
                $originalFilePath,
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
            now($mutatedCode),
            now(''),
            now(''),
        );
    }
}
