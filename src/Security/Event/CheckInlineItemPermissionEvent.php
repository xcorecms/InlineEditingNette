<?php
declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Security\Event;

use Nette\SmartObject;

/**
 * @author Jakub Janata <jakubjanata@gmail.com>
 */
class CheckInlineItemPermissionEvent
{
    use SmartObject;

    /**
     * @var bool
     */
    private $editationAllowed = false;

    /**
     * @var string
     */
    private $namespace;

    /**
     * @var string
     */
    private $locale;

    /**
     * @var string
     */
    private $name;

    /**
     * @param string $namespace
     * @param string $locale
     * @param string $name
     */
    public function __construct(string $namespace, string $locale, string $name)
    {
        $this->namespace = $namespace;
        $this->locale = $locale;
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
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
