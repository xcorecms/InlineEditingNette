<?php
declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Security\Event;

use Nette\SmartObject;

/**
 * @author Jakub Janata <jakubjanata@gmail.com>
 */
class CheckInlineEntityPermissionEvent
{
    use SmartObject;

    /**
     * @var bool
     */
    private $editationAllowed = false;

    /**
     * @var mixed
     */
    private $entity;

    /**
     * @param mixed $entity
     */
    public function __construct($entity)
    {
        $this->entity = $entity;
    }

    /**
     * @return mixed
     */
    public function getEntity()
    {
        return $this->entity;
    }

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
