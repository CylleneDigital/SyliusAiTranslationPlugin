<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * The plugin's whole dependency on the engine (cyllene-digital/ai-translation-bundle), as a
 * list: every engine symbol `src/` and `config/` import is one of these, and each of
 * these is still imported. A new coupling therefore shows up here, in the diff, instead
 * of surfacing as a broken build on the engine's next release.
 *
 * Every entry is named by the engine's written contract (its docs/public-contract.md):
 * adding one here is the moment to check that the page names it too — a class or a method
 * it does not list is internal, whatever PHP says of its visibility.
 */
final class EngineContractTest extends TestCase
{
    private const string ENGINE = 'CylleneDigital\\AiTranslationBundle\\';

    private const array CONTRACT = [
        // Host-facing interfaces the plugin implements or calls.
        'Catalogue\\LocaleProviderInterface',
        'Override\\AuthorProviderInterface',
        'Override\\TranslationManagerInterface',
        'Scope\\ScopeLocalesProviderInterface',
        'Scope\\ScopeProviderInterface',
        'Suggestion\\TranslationAiServiceInterface',
        // Services.
        'Coverage\\CoverageCalculator',
        'Estimation\\GenerationCostEstimator',
        'Override\\OverrideReader',
        'Override\\TranslationValueValidator',
        'Provider\\TranslationProviderRegistry',
        'Repository\\TranslationSuggestionRepository',
        'Scope\\ScopeParametersManager',
        'Suggestion\\PlaceholderConsistencyChecker',
        'Transfer\\OverrideExporter',
        'Transfer\\OverrideImporter',
        'Transfer\\ViewExporter',
        // Entities.
        'Entity\\GenerationLog',
        'Entity\\TranslationOverride',
        'Entity\\TranslationSuggestion',
        // Exceptions.
        'Override\\InvalidOverrideException',
        'Override\\TranslationValueInvalidException',
        'Suggestion\\PlaceholderMismatchException',
        'Suggestion\\SuggestionAlreadyReviewedException',
        'Suggestion\\SuggestionValueMissingException',
        // Value objects, enum, static helpers and the Messenger message.
        'Catalogue\\TranslationCatalogue',
        'Message\\GenerateSuggestionsMessage',
        'Override\\OverrideChange',
        'Suggestion\\PluralCategories',
        'Transfer\\OverrideImportIssue',
        'Transfer\\OverrideImportTemplates',
    ];

    /**
     * What the listed services hand back and the plugin reads without importing it — the
     * controllers and the templates walk these objects, so they are as much a dependency
     * as an import. The engine's contract names each member read here.
     * Class => [the call that returns it, the members read].
     */
    private const array RETURNED_AND_READ = [
        'Coverage\\CoverageReport' => [
            ['Coverage\\CoverageCalculator', 'getReport'],
            ['defaultLocale', 'locales'],
        ],
        'Coverage\\LocaleCoverage' => [
            null,
            ['locale', 'percent', 'missing', 'complete', 'pendingSuggestions'],
        ],
        // Entities: their getters called from PHP are PHPStan's business, these are the ones
        // only the templates (and a findBy) read.
        'Entity\\GenerationLog' => [
            null,
            ['id', 'scope', 'createdAt', 'catalogue', 'sourceLocale', 'targetLocale', 'provider', 'isFailure', 'errorMessage', 'suggestionsCreated'],
        ],
        'Entity\\TranslationSuggestion' => [
            null,
            ['id', 'key', 'catalogue', 'locale', 'scope', 'sourceLocale', 'sourceValue', 'suggestedValue', 'generationError', 'metadata'],
        ],
        'Estimation\\GenerationEstimate' => [
            ['Estimation\\GenerationCostEstimator', 'estimate'],
            ['keys', 'characters', 'chunks', 'keysBeyondCap', 'inputTokens', 'outputTokens', 'cost', 'currency', 'costIsLowerBound', 'quotaRemaining', 'quotaLimit', 'isCapped', 'isPriced'],
        ],
        'Transfer\\OverrideImportPreview' => [
            ['Transfer\\OverrideImporter', 'preview'],
            ['importable', 'skipped', 'unchanged', 'reverted', 'byLocale', 'issues'],
        ],
        'Transfer\\OverrideImportResult' => [
            ['Transfer\\OverrideImporter', 'import'],
            ['imported', 'skipped', 'unchanged', 'reverted', 'issues'],
        ],
    ];

    public function testTheObjectsThePluginReadsStillCarryWhatItReads(): void
    {
        foreach (self::RETURNED_AND_READ as $class => [$producer, $members]) {
            $class = self::ENGINE . $class;
            self::assertTrue(class_exists($class), \sprintf('%s no longer exists.', $class));

            if (null !== $producer) {
                $returnType = (new \ReflectionMethod(self::ENGINE . $producer[0], $producer[1]))->getReturnType();
                self::assertSame($class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : null, \sprintf('%s::%s() no longer returns %s.', $producer[0], $producer[1], $class));
            }

            foreach ($members as $member) {
                self::assertTrue(
                    property_exists($class, $member) || method_exists($class, $member) || method_exists($class, 'get' . ucfirst($member)) || method_exists($class, 'is' . ucfirst($member)),
                    \sprintf('%s no longer exposes "%s".', $class, $member),
                );
            }
        }
    }

    public function testThePluginOnlyImportsTheListedEngineSymbols(): void
    {
        $listed = self::CONTRACT;
        sort($listed);

        self::assertSame($listed, $this->importedEngineSymbols());
    }

    /** @return list<string> the engine symbols imported by the plugin, without the engine prefix */
    private function importedEngineSymbols(): array
    {
        $root = \dirname(__DIR__, 2);
        $symbols = [];

        foreach ((new Finder())->files()->name('*.php')->in([$root . '/src', $root . '/config']) as $file) {
            preg_match_all('/^use ' . preg_quote(self::ENGINE, '/') . '([\w\\\\]+);/m', $file->getContents(), $matches);

            foreach ($matches[1] as $symbol) {
                $symbols[$symbol] = true;
            }
        }

        $symbols = array_keys($symbols);
        sort($symbols);

        return $symbols;
    }
}
