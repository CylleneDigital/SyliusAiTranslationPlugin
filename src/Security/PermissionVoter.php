<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants a {@see Permission} to whoever holds its role, or the role holding them all. The
 * roles go through the access decision manager, so a host's `role_hierarchy` applies: a
 * role of its own may carry any of them.
 *
 * @extends Voter<string, mixed>
 */
final class PermissionVoter extends Voter
{
    /** @var \WeakMap<TokenInterface, array<string, bool>> the grids ask for the same permission on every cell */
    private \WeakMap $decisions;

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {
        $this->decisions = new \WeakMap();
    }

    #[\Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return null !== Permission::tryFrom($attribute);
    }

    #[\Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $decisions = $this->decisions[$token] ?? [];

        if (!isset($decisions[$attribute])) {
            $decisions[$attribute] = $this->decide(Permission::from($attribute), $token);
            $this->decisions[$token] = $decisions;
        }

        return $decisions[$attribute];
    }

    private function decide(Permission $permission, TokenInterface $token): bool
    {
        if ($this->holds($token, Permission::MANAGE_ROLE) || $this->holds($token, $permission->role())) {
            return true;
        }

        // Nobody acts on a screen they cannot open: any permission lets its holder read.
        if (Permission::View === $permission) {
            foreach (Permission::cases() as $other) {
                if (Permission::View !== $other && $this->holds($token, $other->role())) {
                    return true;
                }
            }
        }

        return false;
    }

    private function holds(TokenInterface $token, string $role): bool
    {
        return $this->accessDecisionManager->decide($token, [$role]);
    }
}
