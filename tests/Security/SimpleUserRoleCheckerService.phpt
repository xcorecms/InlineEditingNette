<?php

declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Tests\Security;

use Nette\Security\IIdentity;
use Nette\Security\SimpleIdentity;
use Nette\Security\User;
use Nette\Security\UserStorage;
use stdClass;
use Tester\Assert;
use XcoreCMS\InlineEditingNette\Security\InlinePermissionChecker;
use XcoreCMS\InlineEditingNette\Security\SimpleUserRoleCheckerService;

require __DIR__ . '/../bootstrap.php';

/**
 * @param string[] $roles
 */
function createUser(array $roles, bool $loggedIn = true): User
{
    $storage = new class ($roles, $loggedIn) implements UserStorage {
        /** @param string[] $roles */
        public function __construct(private array $roles, private bool $loggedIn)
        {
        }

        public function saveAuthentication(IIdentity $identity): void
        {
        }

        public function clearAuthentication(bool $clearIdentity): void
        {
        }

        public function getState(): array
        {
            return [$this->loggedIn, new SimpleIdentity(1, $this->roles), null];
        }

        public function setExpiration(?string $expire, bool $clearIdentity): void
        {
        }
    };

    return new User($storage);
}

// user with allowed role -> everything allowed
$checker = new InlinePermissionChecker;
new SimpleUserRoleCheckerService(['admin'], $checker, createUser(['user', 'admin']));

Assert::true($checker->isGlobalEditationAllowed());
Assert::true($checker->isItemEditationAllowed('ns', 'cs', 'title'));
Assert::true($checker->isEntityEditationAllowed(new stdClass));

// user without allowed role -> everything denied
$checker = new InlinePermissionChecker;
new SimpleUserRoleCheckerService(['admin'], $checker, createUser(['user']));

Assert::false($checker->isGlobalEditationAllowed());
Assert::false($checker->isItemEditationAllowed('ns', 'cs', 'title'));
Assert::false($checker->isEntityEditationAllowed(new stdClass));

// anonymous user -> denied
$checker = new InlinePermissionChecker;
new SimpleUserRoleCheckerService(['admin'], $checker, createUser([], false));

Assert::false($checker->isGlobalEditationAllowed());
