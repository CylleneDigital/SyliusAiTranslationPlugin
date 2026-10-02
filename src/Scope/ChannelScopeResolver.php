<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Scope;

use CylleneDigital\AiTranslationBundle\Coverage\CoverageCalculator;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The page-level channel filter of the back-office screens: resolves the `scope` query
 * parameter to a known channel code ('' = global view) and tells which locales that
 * channel exposes, so every block of a screen — coverage, counters, browser, generation
 * form — is computed for the same channel.
 */
final readonly class ChannelScopeResolver
{
    public function __construct(
        private TranslationManagerInterface $translationManager,
        private ChannelRegistry $channels,
        private CoverageCalculator $coverageCalculator,
        private RequestStack $requestStack,
    ) {
    }

    /** The bundle's resolved default locale — the coverage reference (see CoverageCalculator::getDefaultLocale()). */
    public function getDefaultLocale(): string
    {
        return $this->coverageCalculator->getDefaultLocale();
    }

    /**
     * The locale whose texts serve as the reading reference when translating by hand:
     * the channel's default locale when a channel is selected (what that channel is
     * written in), the coverage default locale otherwise. Display-only — "missing" stays
     * defined against the default locale, like the coverage.
     */
    public function getReferenceLocale(string $scope): string
    {
        if ('' !== $scope) {
            $code = $this->channel($scope)?->getDefaultLocale()?->getCode();

            if (null !== $code && '' !== $code) {
                return $code;
            }
        }

        return $this->getDefaultLocale();
    }

    /** The scope carried by the request's query string, '' when absent or unknown. */
    public function fromRequest(Request $request): string
    {
        return $this->resolve($request->query->getString('scope'));
    }

    /**
     * The scope of the page being rendered ('' when there is no request or no known
     * channel) — the grids, which Sylius builds without a request of their own, resolve
     * the page's channel this way.
     */
    public function fromMainRequest(): string
    {
        $request = $this->requestStack->getMainRequest();

        return null !== $request ? $this->fromRequest($request) : '';
    }

    /** A known channel code, or '' (the global view) for anything else. */
    public function resolve(?string $scope): string
    {
        $scope = trim((string) $scope);

        return '' !== $scope && isset($this->getAvailableScopes()[$scope]) ? $scope : '';
    }

    /**
     * A posted scope as a known channel code ('' = the global view) — null when a
     * non-empty value matches no channel, so the callers can refuse it instead of
     * downgrading it to a global write.
     */
    public function resolveOrNull(?string $requested): ?string
    {
        $requested = trim((string) $requested);
        $scope = $this->resolve($requested);

        return '' !== $requested && '' === $scope ? null : $scope;
    }

    /**
     * The selectable scopes (channel code => channel name) — empty hides the selector.
     *
     * @return array<string, string>
     */
    public function getAvailableScopes(): array
    {
        return $this->translationManager->getAvailableScopes();
    }

    /**
     * The locales a channel exposes, in the host's locale order — null for the global view
     * (every available locale) and for a channel without a usable locale.
     *
     * @return list<string>|null
     */
    public function getLocales(string $scope): ?array
    {
        if ('' === $scope) {
            return null;
        }

        $channel = $this->channel($scope);

        if (null === $channel) {
            return null;
        }

        $channelLocales = [];
        foreach ($channel->getLocales() as $locale) {
            if (null !== $locale->getCode()) {
                $channelLocales[$locale->getCode()] = true;
            }
        }

        $locales = array_values(array_filter(
            $this->translationManager->getAvailableLocales(),
            static fn (string $locale): bool => isset($channelLocales[$locale]),
        ));

        return [] !== $locales ? $locales : null;
    }

    private function channel(string $code): ?ChannelInterface
    {
        $channel = $this->channels->get($code);

        return $channel instanceof ChannelInterface ? $channel : null;
    }

    /**
     * The route parameters that carry a scope along — nothing for the global view, so
     * links stay clean.
     *
     * @return array<string, string>
     */
    public static function routeParameters(string $scope): array
    {
        return '' !== $scope ? ['scope' => $scope] : [];
    }
}
