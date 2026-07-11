<?php

declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Latte;

use Latte\Compiler\Tag;
use Latte\Engine;
use Latte\Extension;
use XcoreCMS\InlineEditing\Model\Simple\ContentProvider;
use XcoreCMS\InlineEditingNette\Latte\Nodes\InlineEntityBlockNode;
use XcoreCMS\InlineEditingNette\Latte\Nodes\InlineEntityNode;
use XcoreCMS\InlineEditingNette\Latte\Nodes\InlineNamespaceNode;
use XcoreCMS\InlineEditingNette\Latte\Nodes\InlineNode;
use XcoreCMS\InlineEditingNette\Latte\Nodes\InlineSourceNode;
use XcoreCMS\InlineEditingNette\Security\InlinePermissionChecker;

/**
 * @author Jakub Janata <jakubjanata@gmail.com>
 */
final class InlineEditingLatteExtension extends Extension
{
    private ContentProvider $contentProvider;

    private InlinePermissionChecker $permissionChecker;

    /** @var object|null translator service exposing getLocale() */
    private ?object $translator;

    public function __construct(
        ContentProvider $contentProvider,
        InlinePermissionChecker $permissionChecker,
        ?object $translator = null
    ) {
        $this->contentProvider = $contentProvider;
        $this->permissionChecker = $permissionChecker;
        $this->translator = $translator;
    }

    /**
     * @return array<string, callable>
     */
    public function getTags(): array
    {
        $useTranslator = $this->translator !== null;

        // wrappers must be generator functions, otherwise Latte does not register the n:attribute variants
        return [
            'inline' => static function (Tag $tag) use ($useTranslator): \Generator {
                return yield from InlineNode::create($tag, $useTranslator);
            },
            'inlineNamespace' => [InlineNamespaceNode::class, 'create'],
            'inlineEntityBlock' => [InlineEntityBlockNode::class, 'create'],
            'inlineEntity' => static function (Tag $tag): \Generator {
                return yield from InlineEntityNode::create($tag, false, 'entity-specific');
            },
            'inlineEntityHtml' => static function (Tag $tag): \Generator {
                return yield from InlineEntityNode::create($tag, false, 'entity');
            },
            'inlineField' => static function (Tag $tag): \Generator {
                return yield from InlineEntityNode::create($tag, true, 'entity-specific');
            },
            'inlineFieldHtml' => static function (Tag $tag): \Generator {
                return yield from InlineEntityNode::create($tag, true, 'entity');
            },
            'inlineSource' => [InlineSourceNode::class, 'create'],
        ];
    }

    /**
     * @return array<string, callable>
     */
    public function getFilters(): array
    {
        return [
            'inlineEditingContent' => function (?string $namespace, ?string $locale, string $name): string {
                return $this->contentProvider->getContent((string) $namespace, (string) $locale, $name);
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getProviders(): array
    {
        $providers = ['inlinePermissionChecker' => $this->permissionChecker];

        if ($this->translator !== null) {
            $providers['inlineTranslatorProvider'] = $this->translator;
        }

        return $providers;
    }

    public function getCacheKey(Engine $engine): mixed
    {
        return ['useTranslator' => $this->translator !== null];
    }
}
