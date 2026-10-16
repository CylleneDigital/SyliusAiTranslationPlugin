<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Unit\Bridge;

use CylleneDigital\SyliusAiTranslationPlugin\Bridge\SyliusChannelScopeProvider;
use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelRegistry;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface as CoreChannelInterface;
use Sylius\Component\Locale\Model\Locale;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RequestStack;

final class SyliusChannelScopeProviderTest extends TestCase
{
    public function testTheScopesAreTheChannelsNamedOrElseCoded(): void
    {
        $repository = $this->createStub(ChannelRepositoryInterface::class);
        $repository->method('findAll')->willReturn([$this->channel('B2B', 'Business'), $this->channel('WEB', null)]);

        $provider = new SyliusChannelScopeProvider($this->createStub(ChannelContextInterface::class), new ChannelRegistry($repository, new RequestStack(), new MockClock()));

        self::assertSame(['B2B' => 'Business', 'WEB' => 'WEB'], $provider->getAvailableScopes());
    }

    public function testTheScopeIsTheCurrentChannel(): void
    {
        $context = $this->createStub(ChannelContextInterface::class);
        $context->method('getChannel')->willReturn($this->channel('B2B', 'Business'));

        self::assertSame('B2B', $this->provider($context)->getScope());
    }

    public function testThereIsNoScopeWithoutAChannelContext(): void
    {
        $context = $this->createStub(ChannelContextInterface::class);
        $context->method('getChannel')->willThrowException(new ChannelNotFoundException());

        self::assertNull($this->provider($context)->getScope());
    }

    public function testTheLocalesOfAScopeAreTheChannelsOwn(): void
    {
        $locales = [];

        // A locale without a code yet (a form being filled) is left out.
        foreach (['en_US', null, 'fr_FR'] as $code) {
            $locale = new Locale();
            $locale->setCode($code);
            $locales[] = $locale;
        }

        $channel = $this->createStub(CoreChannelInterface::class);
        $channel->method('getCode')->willReturn('B2B');
        $channel->method('getLocales')->willReturn(new ArrayCollection($locales));

        $bare = $this->createStub(CoreChannelInterface::class);
        $bare->method('getCode')->willReturn('BARE');
        $bare->method('getLocales')->willReturn(new ArrayCollection());

        // The scope provider may be handed a channel of another model: it offers every locale.
        $plain = $this->channel('PLAIN', null);

        $repository = $this->createStub(ChannelRepositoryInterface::class);
        $repository->method('findAll')->willReturn([$channel, $bare, $plain]);

        $provider = new SyliusChannelScopeProvider($this->createStub(ChannelContextInterface::class), new ChannelRegistry($repository, new RequestStack(), new MockClock()));

        self::assertSame(['en_US', 'fr_FR'], $provider->getScopeLocales('B2B'));
        // No locale to go by: every locale. An unknown channel is refused earlier, by the
        // engine's scope check.
        self::assertNull($provider->getScopeLocales('BARE'));
        self::assertNull($provider->getScopeLocales('PLAIN'));
        self::assertNull($provider->getScopeLocales('GONE'));
    }

    private function provider(ChannelContextInterface $context): SyliusChannelScopeProvider
    {
        return new SyliusChannelScopeProvider($context, new ChannelRegistry($this->createStub(ChannelRepositoryInterface::class), new RequestStack(), new MockClock()));
    }

    private function channel(string $code, ?string $name): ChannelInterface
    {
        $channel = $this->createStub(ChannelInterface::class);
        $channel->method('getCode')->willReturn($code);
        $channel->method('getName')->willReturn($name);

        return $channel;
    }
}
