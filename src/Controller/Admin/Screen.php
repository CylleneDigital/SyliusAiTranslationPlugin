<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin;

use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelScopeResolver;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * What the actions of one admin screen share: the CSRF intention of everything it writes,
 * its flashes, and the way back to its list. One instance per screen (catalogue,
 * suggestions), each with its own intention and index route.
 */
final readonly class Screen
{
    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
        private string $csrfIntention,
        private string $indexRoute,
    ) {
    }

    /**
     * A 403, not `#[IsCsrfTokenValid]`: the attribute throws an authentication exception,
     * which sends a logged-in admin to the login page.
     */
    public function validateCsrf(Request $request): void
    {
        if (!$this->isTokenValid($this->csrfIntention, $request->request->get('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }
    }

    public function isTokenValid(string $id, mixed $token): bool
    {
        return \is_string($token) && $this->csrfTokenManager->isTokenValid(new CsrfToken($id, $token));
    }

    public function isScreenTokenValid(mixed $token): bool
    {
        return $this->isTokenValid($this->csrfIntention, $token);
    }

    /**
     * @param string|array{message: string, parameters: array<string, mixed>} $message a key of the flashes domain, with its parameters when it has some
     */
    public function flash(Request $request, string $type, string|array $message): void
    {
        $session = $request->getSession();

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }
    }

    /**
     * Back to the list the action came from — same channel, filters, sorting and page
     * (the referer, when it is this screen) — else the channel's fresh list. Only the
     * referer's query string is reused: the redirect always targets this screen's own path.
     */
    public function redirectBack(Request $request, string $scope): RedirectResponse
    {
        $referer = $request->headers->get('referer');

        if (\is_string($referer) && '' !== $referer) {
            $indexPath = $this->urlGenerator->generate($this->indexRoute);
            $refererHost = parse_url($referer, \PHP_URL_HOST);

            if (parse_url($referer, \PHP_URL_PATH) === $indexPath && (null === $refererHost || $refererHost === $request->getHost())) {
                $query = parse_url($referer, \PHP_URL_QUERY);

                return new RedirectResponse($indexPath . (\is_string($query) && '' !== $query ? '?' . $query : ''));
            }
        }

        return $this->redirectToIndex($scope);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function redirectToIndex(string $scope, array $parameters = []): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate($this->indexRoute, $parameters + ChannelScopeResolver::routeParameters($scope)));
    }
}
