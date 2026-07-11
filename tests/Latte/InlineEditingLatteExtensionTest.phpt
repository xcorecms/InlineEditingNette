<?php

declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Tests\Latte;

use Latte\Engine;
use Latte\Loaders\StringLoader;
use Tester\Assert;
use Tester\TestCase;
use XcoreCMS\InlineEditing\Model\Simple\ContentProvider;
use XcoreCMS\InlineEditingNette\Latte\InlineEditingLatteExtension;
use XcoreCMS\InlineEditingNette\Security\Event\CheckInlineEntityPermissionEvent;
use XcoreCMS\InlineEditingNette\Security\Event\CheckInlineGlobalPermissionEvent;
use XcoreCMS\InlineEditingNette\Security\Event\CheckInlineItemPermissionEvent;
use XcoreCMS\InlineEditingNette\Security\InlinePermissionChecker;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
class InlineEditingLatteExtensionTest extends TestCase
{
    private function createContentProvider(): ContentProvider
    {
        return new class extends ContentProvider {
            public function __construct()
            {
            }

            public function getContent(string $namespace, string $locale, string $name): string
            {
                return "#$namespace|$locale|$name#";
            }
        };
    }

    private function createLatte(bool $allowed, ?object $translator = null): Engine
    {
        $checker = new InlinePermissionChecker;

        if ($allowed) {
            $checker->onCheckGlobalPermission[] = function (CheckInlineGlobalPermissionEvent $event): void {
                $event->setAllowed();
            };
            $checker->onCheckItemPermission[] = function (CheckInlineItemPermissionEvent $event): void {
                $event->setAllowed();
            };
            $checker->onCheckEntityPermission[] = function (CheckInlineEntityPermissionEvent $event): void {
                $event->setAllowed();
            };
        }

        $latte = new Engine;
        $latte->setLoader(new StringLoader);
        $latte->addExtension(
            new InlineEditingLatteExtension($this->createContentProvider(), $checker, $translator)
        );

        return $latte;
    }

    public function testInlineTag(): void
    {
        $latte = $this->createLatte(false);

        $output = $latte->renderToString('{inline title, namespace => web, locale => cs}{/inline}');
        Assert::same('#web|cs|title#', $output);
    }

    public function testInlineTagWithoutNamespaceAndLocale(): void
    {
        $latte = $this->createLatte(false);

        $output = $latte->renderToString('{inline title}{/inline}');
        Assert::same('#||title#', $output);
    }

    public function testInlineNamespaceTag(): void
    {
        $latte = $this->createLatte(false);

        $output = $latte->renderToString(
            '{inlineNamespace web}{inline title, locale => cs}{/inline}{/inlineNamespace}{inline out}{/inline}'
        );
        Assert::same('#web|cs|title##||out#', $output);
    }

    public function testInlineNAttributeAllowed(): void
    {
        $latte = $this->createLatte(true);

        $output = $latte->renderToString('<div n:inline="title, namespace => web, locale => cs">x</div>');
        Assert::contains('data-inline-type="simple"', $output);
        Assert::contains('data-inline-name="title"', $output);
        Assert::contains('data-inline-namespace="web"', $output);
        Assert::contains('data-inline-locale="cs"', $output);
        Assert::contains('>#web|cs|title#</div>', $output);
    }

    public function testInlineNAttributeDenied(): void
    {
        $latte = $this->createLatte(false);

        $output = $latte->renderToString('<div n:inline="title, namespace => web, locale => cs">x</div>');
        Assert::notContains('data-inline', $output);
        Assert::same('<div>#web|cs|title#</div>', $output);
    }

    public function testInlineTagWithTranslatorLocale(): void
    {
        $translator = new class {
            public function getLocale(): string
            {
                return 'en';
            }
        };

        $latte = $this->createLatte(false, $translator);

        $output = $latte->renderToString('{inline title, namespace => web}{/inline}');
        Assert::same('#web|en|title#', $output);
    }

    public function testInlineEntityNAttribute(): void
    {
        $latte = $this->createLatte(true);
        $entity = new class {
            public int $id = 7;
            public string $name = 'Hello';
        };

        $output = $latte->renderToString(
            '<div n:inlineEntity="$entity, \'name\'">x</div>',
            ['entity' => $entity]
        );

        Assert::contains('data-inline-type="entity-specific"', $output);
        Assert::contains('data-inline-id="7"', $output);
        Assert::contains('data-inline-property="name"', $output);
        Assert::contains('>Hello</div>', $output);
    }

    public function testInlineFieldInsideEntityBlock(): void
    {
        $latte = $this->createLatte(true);
        $entity = new class {
            public int $id = 7;
            public string $name = 'Hello';
        };

        $output = $latte->renderToString(
            '{inlineEntityBlock $entity}<span n:inlineField="name">x</span>{/inlineEntityBlock}',
            ['entity' => $entity]
        );

        Assert::contains('data-inline-type="entity-specific"', $output);
        Assert::contains('data-inline-property="name"', $output);
        Assert::contains('>Hello</span>', $output);
    }

    public function testInlineSourceTag(): void
    {
        $allowed = $this->createLatte(true)
            ->renderToString('{inlineSource}', ['baseUrl' => 'http://example.com']);
        Assert::contains('<script src="http://example.com/inline/inline.js"', $allowed);
        Assert::contains('data-source-gateway-url="http://example.com/inline-editing"', $allowed);

        $denied = $this->createLatte(false)
            ->renderToString('{inlineSource}', ['baseUrl' => 'http://example.com']);
        Assert::same('', $denied);
    }

    public function testFilter(): void
    {
        $extension = new InlineEditingLatteExtension($this->createContentProvider(), new InlinePermissionChecker);

        $filter = $extension->getFilters()['inlineEditingContent'];
        Assert::same('#web|cs|title#', $filter('web', 'cs', 'title'));
        Assert::same('#||title#', $filter(null, null, 'title'));
    }

    public function testProvidersAndCacheKey(): void
    {
        $contentProvider = $this->createContentProvider();
        $checker = new InlinePermissionChecker;
        $translator = new class {
            public function getLocale(): string
            {
                return 'en';
            }
        };

        $withoutTranslator = new InlineEditingLatteExtension($contentProvider, $checker);
        $providers = $withoutTranslator->getProviders();
        Assert::same($checker, $providers['inlinePermissionChecker']);
        Assert::false(isset($providers['inlineTranslatorProvider']));

        $withTranslator = new InlineEditingLatteExtension($contentProvider, $checker, $translator);
        $providers = $withTranslator->getProviders();
        Assert::same($translator, $providers['inlineTranslatorProvider']);

        $engine = new Engine;
        Assert::notSame($withoutTranslator->getCacheKey($engine), $withTranslator->getCacheKey($engine));
    }
}

(new InlineEditingLatteExtensionTest)->run();
