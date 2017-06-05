<?php
declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Security;

use Nette\Security\User;
use Nette\SmartObject;
use XcoreCMS\InlineEditingNette\Security\Event\CheckInlineGlobalPermissionEvent;

/**
 * @author Jakub Janata <jakubjanata@gmail.com>
 */
class SimpleUserRoleCheckerService
{
    use SmartObject;

    /**
     * @param array $roles
     * @param InlinePermissionChecker $checker
     * @param User $user
     */
    public function __construct(array $roles, InlinePermissionChecker $checker, User $user)
    {
        $checker->onCheckGlobalPermission[] = function (CheckInlineGlobalPermissionEvent $event) use ($roles, $user) {
            foreach ($user->getRoles() as $role) {
                if (in_array($role, $roles, true)) {
                    $event->setAllowed();
                    return;
                }
            }
        };
    }
}
