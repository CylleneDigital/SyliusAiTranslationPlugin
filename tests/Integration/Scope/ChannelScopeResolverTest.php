<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Integration\Scope;

use CylleneDigital\AiTranslationBundle\Coverage\CoverageCalculator;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelRegistry;
use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelScopeResolver;
use Doctrine\Common\Collections\ArrayCollection;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\SyncsTestSchema;

/**
 * The resolver over stubbed channels and a stubbed translation manager; the engine's
 * coverage calculator (a final class, not an interface) is the container's own — its
 * default locale is the test application's, en_US.
 */
final class ChannelScopeResolverTest extends KernelTestCase
{
    use SyncsTestSchema;

    private int $channelLookups = 0;

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        self::syncTestSchema();
        self::ensureKernelShutdown();
    }

    public function testOnlyAKnownChannelCodeSurvivesResolution(): void
    {
        $resolver = $this->createResolver(['FASHION_WEB' => 'Fashion', 'B2B' => 'Business']);

        self::assertSame('', $resolver->resolve(null));
        self::assertSame('', $resolver->resolve('  '));
        self::assertSame('', $resolver->resolve('UNKNOWN'));
        self::assertSame('B2B', $resolver->resolve('B2B'));
        self::assertSame('FASHION_WEB', $resolver->fromRequest(new Request(['scope' => 'FASHION_WEB'])));
        self::assertSame('', $resolver->fromRequest(new Request()));
        // No request at all (CLI, workers): the global view.
        self::assertSame('', $resolver->fromMainRequest());

        $stack = new RequestStack();
        $stack->push(new Request(['scope' => 'B2B']));
        self::assertSame('B2B', $this->createResolver(['FASHION_WEB' => 'Fashion', 'B2B' => 'Business'], requestStack: $stack)->fromMainRequest());
    }

    public function testResolveOrNullDistinguishesUnknownFromGlobal(): void
    {
        $resolver = $this->createResolver(['B2B' => 'Business']);

        self::assertSame('', $resolver->resolveOrNull(''));
        self::assertSame('', $resolver->resolveOrNull(null));
        self::assertSame('', $resolver->resolveOrNull('  '));
        self::assertSame('B2B', $resolver->resolveOrNull('B2B'));
        self::assertNull($resolver->resolveOrNull('UNKNOWN'));
    }

    public function testTheChannelLocalesFollowTheHostOrderAndDropUnknownOnes(): void
    {
        // Host order: fr_FR, en_US, de_DE. The channel declares de_DE, fr_FR and a locale
        // the host does not expose.
        $resolver = $this->createResolver(['B2B' => 'Business'], ['B2B' => ['de_DE', 'fr_FR', 'xx_XX']]);

        self::assertSame(['fr_FR', 'de_DE'], $resolver->getLocales('B2B'));
    }

    public function testTheGlobalViewAndUnknownChannelsHaveNoLocaleRestriction(): void
    {
        $resolver = $this->createResolver(['B2B' => 'Business'], ['B2B' => ['xx_XX']]);

        self::assertNull($resolver->getLocales(''));
        self::assertNull($resolver->getLocales('UNKNOWN'));
        // A channel whose locales the host does not expose at all: no restriction either.
        self::assertNull($resolver->getLocales('B2B'));
    }

    public function testRouteParametersOnlyCarryANonGlobalScope(): void
    {
        self::assertSame([], ChannelScopeResolver::routeParameters(''));
        self::assertSame(['scope' => 'B2B'], ChannelScopeResolver::routeParameters('B2B'));
    }

    public function testTheReferenceLocaleIsTheChannelDefaultThenTheDefaultLocale(): void
    {
        $resolver = $this->createResolver(
            ['B2B' => 'Business', 'NO_DEFAULT' => 'No default'],
            ['B2B' => ['de_DE', 'fr_FR'], 'NO_DEFAULT' => ['fr_FR']],
            defaultLocales: ['B2B' => 'de_DE'],
        );

        self::assertSame('en_US', $resolver->getDefaultLocale());
        // A channel with a default locale: that is what its texts are written in.
        self::assertSame('de_DE', $resolver->getReferenceLocale('B2B'));
        // No channel, unknown channel, or channel without a default: the default locale.
        self::assertSame('en_US', $resolver->getReferenceLocale(''));
        self::assertSame('en_US', $resolver->getReferenceLocale('UNKNOWN'));
        self::assertSame('en_US', $resolver->getReferenceLocale('NO_DEFAULT'));
    }

    public function testTheChannelsAreReadOnceWhateverIsAsked(): void
    {
        $resolver = $this->createResolver(['B2B' => 'Business'], ['B2B' => ['fr_FR']], defaultLocales: ['B2B' => 'fr_FR']);

        $resolver->getLocales('B2B');
        $resolver->getReferenceLocale('B2B');
        $resolver->getLocales('B2B');
        $resolver->getLocales('UNKNOWN');
        $resolver->getReferenceLocale('UNKNOWN');

        self::assertSame(1, $this->channelLookups);
    }

    /**
     * @param array<string, string>       $scopes         channel code => name
     * @param array<string, list<string>> $locales        channel code => its locale codes
     * @param array<string, string>       $defaultLocales channel code => its default locale code
     */
    private function createResolver(array $scopes, array $locales = [], array $defaultLocales = [], ?RequestStack $requestStack = null): ChannelScopeResolver
    {
        $manager = $this->createStub(TranslationManagerInterface::class);
        $manager->method('getAvailableScopes')->willReturn($scopes);
        $manager->method('getAvailableLocales')->willReturn(['fr_FR', 'en_US', 'de_DE']);

        $channels = [];
        foreach ($locales as $code => $localeCodes) {
            $localeObjects = [];
            foreach ($localeCodes as $localeCode) {
                $locale = $this->createStub(LocaleInterface::class);
                $locale->method('getCode')->willReturn($localeCode);
                $localeObjects[] = $locale;
            }

            $channel = $this->createStub(ChannelInterface::class);
            $channel->method('getCode')->willReturn($code);
            $channel->method('getLocales')->willReturn(new ArrayCollection($localeObjects));

            if (isset($defaultLocales[$code])) {
                $defaultLocale = $this->createStub(LocaleInterface::class);
                $defaultLocale->method('getCode')->willReturn($defaultLocales[$code]);
                $channel->method('getDefaultLocale')->willReturn($defaultLocale);
            }

            $channels[$code] = $channel;
        }

        $channelRepository = $this->createStub(ChannelRepositoryInterface::class);
        $channelRepository->method('findAll')->willReturnCallback(function () use ($channels): array {
            ++$this->channelLookups;

            return array_values($channels);
        });

        /** @var CoverageCalculator $calculator */
        $calculator = self::getContainer()->get(CoverageCalculator::class);

        return new ChannelScopeResolver($manager, new ChannelRegistry($channelRepository, $requestStack ??= new RequestStack(), new MockClock()), $calculator, $requestStack);
    }
}
