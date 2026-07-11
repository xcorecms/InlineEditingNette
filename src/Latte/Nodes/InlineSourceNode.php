<?php

declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Latte\Nodes;

use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

/**
 * {inlineSource}
 *
 * @author Jakub Janata <jakubjanata@gmail.com>
 */
final class InlineSourceNode extends StatementNode
{
    public static function create(Tag $tag): self
    {
        $node = new self();
        $tag->node = $node;
        return $node;
    }

    public function print(PrintContext $context): string
    {
        return <<<'XX'
            if ($this->global->inlinePermissionChecker->isGlobalEditationAllowed()) {
                $_inline_baseUrl = LR\HtmlHelpers::escapeAttr(\Latte\Essential\Filters::checkUrl($baseUrl));
                echo '<script src="' . $_inline_baseUrl . '/inline/inline.js" id="inline-editing-source"
                data-source-css="' . $_inline_baseUrl . '/inline/inline.css"
                data-source-tinymce-js="' . $_inline_baseUrl . '/inline/tinymce/tinymce.min.js"
                data-source-gateway-url="' . $_inline_baseUrl . '/inline-editing"></script>';
            }

            XX;
    }

    public function &getIterator(): \Generator
    {
        false && yield; // @phpstan-ignore-line - empty by-reference generator
    }
}
