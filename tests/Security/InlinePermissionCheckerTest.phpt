<?php

declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Tests\Security;

use stdClass;
use Tester\Assert;
use Tester\TestCase;
use XcoreCMS\InlineEditingNette\Security\Event\CheckInlineEntityPermissionEvent;
use XcoreCMS\InlineEditingNette\Security\Event\CheckInlineGlobalPermissionEvent;
use XcoreCMS\InlineEditingNette\Security\Event\CheckInlineItemPermissionEvent;
use XcoreCMS\InlineEditingNette\Security\InlinePermissionChecker;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
class InlinePermissionCheckerTest extends TestCase
{
    public function testEverythingDeniedByDefault(): void
    {
        $checker = new InlinePermissionChecker;

        Assert::false($checker->isGlobalEditationAllowed());
        Assert::false($checker->isItemEditationAllowed('ns', 'cs', 'title'));
        Assert::false($checker->isEntityEditationAllowed(new stdClass));
    }

    public function testGlobalAllowed(): void
    {
        $checker = new InlinePermissionChecker;
        $checker->onCheckGlobalPermission[] = function (CheckInlineGlobalPermissionEvent $event): void {
            $event->setAllowed();
        };

        Assert::true($checker->isGlobalEditationAllowed());
    }

    public function testItemDeniedWhenGlobalDenied(): void
    {
        $checker = new InlinePermissionChecker;
        $itemHandlerCalled = false;
        $checker->onCheckItemPermission[] = function (CheckInlineItemPermissionEvent $event) use (&$itemHandlerCalled): void {
            $itemHandlerCalled = true;
            $event->setAllowed();
        };

        Assert::false($checker->isItemEditationAllowed('ns', 'cs', 'title'));
        Assert::false($itemHandlerCalled);
    }

    public function testItemAllowedWhenGlobalAndItemAllowed(): void
    {
        $checker = new InlinePermissionChecker;
        $checker->onCheckGlobalPermission[] = function (CheckInlineGlobalPermissionEvent $event): void {
            $event->setAllowed();
        };
        $checker->onCheckItemPermission[] = function (CheckInlineItemPermissionEvent $event): void {
            Assert::same('ns', $event->getNamespace());
            Assert::same('cs', $event->getLocale());
            Assert::same('title', $event->getName());
            $event->setAllowed();
        };

        Assert::true($checker->isItemEditationAllowed('ns', 'cs', 'title'));
    }

    public function testItemDeniedWithoutItemHandler(): void
    {
        $checker = new InlinePermissionChecker;
        $checker->onCheckGlobalPermission[] = function (CheckInlineGlobalPermissionEvent $event): void {
            $event->setAllowed();
        };

        Assert::false($checker->isItemEditationAllowed('ns', 'cs', 'title'));
    }

    public function testEntityAllowedWhenGlobalAndEntityAllowed(): void
    {
        $checker = new InlinePermissionChecker;
        $entity = new stdClass;

        $checker->onCheckGlobalPermission[] = function (CheckInlineGlobalPermissionEvent $event): void {
            $event->setAllowed();
        };
        $checker->onCheckEntityPermission[] = function (CheckInlineEntityPermissionEvent $event) use ($entity): void {
            Assert::same($entity, $event->getEntity());
            $event->setAllowed();
        };

        Assert::true($checker->isEntityEditationAllowed($entity));
    }

    public function testGlobalResultIsCached(): void
    {
        $checker = new InlinePermissionChecker;
        $calls = 0;
        $checker->onCheckGlobalPermission[] = function (CheckInlineGlobalPermissionEvent $event) use (&$calls): void {
            $calls++;
            $event->setAllowed();
        };

        Assert::true($checker->isGlobalEditationAllowed());
        Assert::true($checker->isGlobalEditationAllowed());
        Assert::same(1, $calls);
    }
}

(new InlinePermissionCheckerTest)->run();
