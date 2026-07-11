<?php

declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Latte\Nodes;

use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\AuxiliaryNode;
use Latte\Compiler\Nodes\Php\ModifierNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

/**
 * {inline name, namespace => x, locale => y} or <div n:inline="...">
 *
 * @author Jakub Janata <jakubjanata@gmail.com>
 */
final class InlineNode extends StatementNode
{
    public ?AreaNode $content = null;

    public ModifierNode $modifier;

    private string $nameCode;

    private string $namespaceCode;

    private string $localeCode;

    private bool $isNAttribute = false;

    /**
     * @return \Generator<int, ?array<string>, array{AreaNode, ?Tag}, static>
     */
    public static function create(Tag $tag, bool $useTranslator): \Generator
    {
        $tag->expectArguments();
        $node = new static();
        $tag->node = $node;

        // read raw arguments (up to filters) - values are compile-time literals as in the original macro
        $stream = $tag->parser->stream;
        $text = '';
        while (!$tag->parser->isEnd() && $stream->peek()->text !== '|') {
            $text .= $stream->consume()->text;
        }
        $node->modifier = $tag->parser->parseModifier();

        $args = explode(',', $text);

        $node->nameCode = var_export(trim($args[0]), true);
        $node->namespaceCode = 'isset($inlineNamespaceStack) ? (string) end($inlineNamespaceStack) : \'\'';
        $node->localeCode = $useTranslator ? '$this->global->inlineTranslatorProvider->getLocale()' : "''";

        // parse params
        foreach ($args as $arg) {
            $item = explode('=>', $arg);

            if (count($item) === 2) {
                switch (trim($item[0])) {
                    case 'namespace':
                        $node->namespaceCode = var_export(trim($item[1]), true);
                        break;
                    case 'locale':
                        $node->localeCode = var_export(trim($item[1]), true);
                        break;
                }
            }
        }

        [$node->content] = yield;

        if ($tag->isNAttribute() && $tag->htmlElement !== null) {
            $node->isNAttribute = true;
            $element = $tag->htmlElement;
            $element->attributes->children[] = new AuxiliaryNode(
                static fn(PrintContext $context): string => $node->printAttributes($context)
            );
            $element->content = new AuxiliaryNode(
                static fn(PrintContext $context): string => $node->printInnerContent($context)
            );
        }

        return $node;
    }

    public function print(PrintContext $context): string
    {
        $setup = '$_inline_namespace = ' . $this->namespaceCode . '; '
            . '$_inline_locale = (string) ' . $this->localeCode . '; '
            . '$_inline_name = ' . $this->nameCode . '; ';

        return $setup . ($this->isNAttribute && $this->content !== null
            ? $this->content->print($context)
            : $this->printInnerContent($context));
    }

    private function printAttributes(PrintContext $context): string
    {
        return <<<'XX'
            if ($this->global->inlinePermissionChecker
                ->isItemEditationAllowed($_inline_namespace, $_inline_locale, $_inline_name)) {
                echo ' data-inline-type="simple"',
                    ' data-inline-name="', LR\HtmlHelpers::escapeAttr($_inline_name), '"',
                    ' data-inline-namespace="', LR\HtmlHelpers::escapeAttr($_inline_namespace), '"',
                    ' data-inline-locale="', LR\HtmlHelpers::escapeAttr($_inline_locale), '"';
            }

            XX;
    }

    private function printInnerContent(PrintContext $context): string
    {
        return $context->format(
            'echo %modify(($this->filters->inlineEditingContent)'
            . '($_inline_namespace, $_inline_locale, $_inline_name)) %line;',
            $this->modifier,
            $this->position,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->modifier;
        if ($this->content !== null) {
            yield $this->content;
        }
    }
}
