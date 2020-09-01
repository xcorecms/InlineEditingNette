<?php

declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Security\Event;

use Nette\SmartObject;

/**
 * @author Jakub Janata <jakubjanata@gmail.com>
 */
class CheckInlineGlobalPermissionEvent
{
    use SmartObject;

    /**
     * @var bool
     */
    private $editationAllowed = false;

    /**
     * @param bool $allowed
     */
    public function setAllowed(bool $allowed = true): void
    {
        $this->editationAllowed = $allowed;
    }

    /**
     * @return bool
     */
    public function isAllowed(): bool
    {
        return $this->editationAllowed;
    }
}
