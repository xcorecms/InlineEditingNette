<?php
declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Security;

use Closure;
use Nette\SmartObject;
use XcoreCMS\InlineEditingNette\Security\Event\CheckInlineEntityPermissionEvent;
use XcoreCMS\InlineEditingNette\Security\Event\CheckInlineGlobalPermissionEvent;
use XcoreCMS\InlineEditingNette\Security\Event\CheckInlineItemPermissionEvent;

/**
 * Check if user is allowed to editing a record:
 * 1.) global IS NOT allowed -> return false
 * 2.) global IS allowed => check user is allowed edit this item -> return true/false
 *
 * @author Jakub Janata <jakubjanata@gmail.com>

 * @method onCheckGlobalPermission(CheckInlineGlobalPermissionEvent $event)
 * @method onCheckItemPermission(CheckInlineItemPermissionEvent $event)
 * @method onCheckEntityPermission(CheckInlineEntityPermissionEvent $event)
 */
class InlinePermissionChecker
{
    use SmartObject;

    /** @var Closure[] */
    public $onCheckGlobalPermission = [];

    /** @var Closure[] */
    public $onCheckItemPermission = [];

    /** @var Closure[] */
    public $onCheckEntityPermission = [];

    /** @var bool|null */
    private $globalEditationAllowed;

    /**
     * @param string $namespace
     * @param string $locale
     * @param string $name
     * @return bool
     */
    public function isItemEditationAllowed(string $namespace, string $locale, string $name): bool
    {
        $isAllowed = $this->isGlobalEditationAllowed();

        // global allowed
        if ($isAllowed === false) {
            return false;
        }

        // no handlers - is allowed
        if (count($this->onCheckItemPermission) === 0) {
            return true;
        }

        // check item permissions
        $event = new CheckInlineItemPermissionEvent($namespace, $locale, $name);
        $this->onCheckItemPermission($event);
        return $event->isAllowed();
    }

    /**
     * @param mixed $entity
     * @return bool
     */
    public function isEntityEditationAllowed($entity): bool
    {
        $isAllowed = $this->isGlobalEditationAllowed();

        // global allowed
        if ($isAllowed === false) {
            return false;
        }

        // no handlers - is allowed
        if (count($this->onCheckEntityPermission) === 0) {
            return true;
        }

        // check entity permissions
        $event = new CheckInlineEntityPermissionEvent($entity);
        $this->onCheckEntityPermission($event);
        return $event->isAllowed();
    }

    /**
     * @return bool
     */
    public function isGlobalEditationAllowed(): bool
    {
        if ($this->globalEditationAllowed === null) {
            $event = new CheckInlineGlobalPermissionEvent;
            $this->onCheckGlobalPermission($event);
            $this->globalEditationAllowed = $event->isAllowed();
        }

        return $this->globalEditationAllowed;
    }
}
