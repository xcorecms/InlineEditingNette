<?php

declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Tests\Security;

use stdClass;
use Tester\Assert;
use XcoreCMS\InlineEditingNette\Security\Event\CheckInlineEntityPermissionEvent;
use XcoreCMS\InlineEditingNette\Security\Event\CheckInlineGlobalPermissionEvent;
use XcoreCMS\InlineEditingNette\Security\Event\CheckInlineItemPermissionEvent;

require __DIR__ . '/../bootstrap.php';

// global permission event
$event = new CheckInlineGlobalPermissionEvent;
Assert::false($event->isAllowed());
$event->setAllowed();
Assert::true($event->isAllowed());
$event->setAllowed(false);
Assert::false($event->isAllowed());

// item permission event
$event = new CheckInlineItemPermissionEvent('ns', 'cs', 'title');
Assert::same('ns', $event->getNamespace());
Assert::same('cs', $event->getLocale());
Assert::same('title', $event->getName());
Assert::false($event->isAllowed());
$event->setAllowed();
Assert::true($event->isAllowed());

// entity permission event
$entity = new stdClass;
$event = new CheckInlineEntityPermissionEvent($entity);
Assert::same($entity, $event->getEntity());
Assert::false($event->isAllowed());
$event->setAllowed();
Assert::true($event->isAllowed());
