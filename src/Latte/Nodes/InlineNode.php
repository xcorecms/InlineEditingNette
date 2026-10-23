<?php

declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Latte\Nodes;

use Latte\CompileException;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\AuxiliaryNode as AreaAuxiliaryNode;
use Latte\Compiler\Nodes\FragmentNode;
use Latte\Compiler\Nodes\Php\Expression\AuxiliaryNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\Php\ModifierNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Compiler\TagLexer;
use Latte\Compiler\TagParser;

/**
 * {inline name, namespace => x, locale => y} or <div n:inline="...">
 *
 * Name, namespace and locale are compile-time strings which support variable interpolation,
 * e.g. n:inline="ok.$x" or n:inline="ok.{$x}". Because the arguments are split by commas,
 * expressions containing a comma (e.g. ok.{$f(1, 2)}) are not supported.
 *
 * @author Jakub Janata <jakubjanata@gmail.com>
 */
final class InlineNode extends StatementNode
{
    public ?AreaNode $content = null;

    public ModifierNode $modifier;

    public ExpressionNode $name;

    public ExpressionNode $namespace;

    public ExpressionNode $locale;

    private bool $isNAttribute = false;

    /**
     * @return \Generator<int, ?array<string>, array{AreaNode, ?Tag}, static>
     */
    public static function create(Tag $tag, bool $useTranslator): \Generator
    {
        $tag->expectArguments();
        $node = new static();
        $tag->node = $node;

        // read raw arguments (up to filters) - values are compile-time strings as in the original macro
        $stream = $tag->parser->stream;
        $text = '';
        while (!$tag->parser->isEnd() && $stream->peek()->text !== '|') {
            $text .= $stream->consume()->text;
        }
        $node->modifier = $tag->parser->parseModifier();

        $args = explode(',', $text);

        $node->name = self::parseValue($args[0], $tag);
        $node->namespace = new AuxiliaryNode(
            static fn(): string => '(isset($inlineNamespaceStack) ? (string) end($inlineNamespaceStack) : \'\')'
        );
        $node->locale = new AuxiliaryNode(
            static fn(): string => $useTranslator ? '$this->global->inlineTranslatorProvider->getLocale()' : "''"
        );

        // parse params
        foreach ($args as $arg) {
            $item = explode('=>', $arg);

            if (count($item) === 2) {
                switch (trim($item[0])) {
                    case 'namespace':
                        $node->namespace = self::parseValue($item[1], $tag);
                        break;
                    case 'locale':
                        $node->locale = self::parseValue($item[1], $tag);
                        break;
                }
            }
        }

        [$node->content] = yield;

        if ($tag->isNAttribute() && $tag->htmlElement !== null) {
            $node->isNAttribute = true;
            $element = $tag->htmlElement;
            $element->attributes ??= new FragmentNode();
            $element->attributes->children[] = new AreaAuxiliaryNode(
                static fn(PrintContext $context): string => $node->printAttributes($context)
            );
            $element->content = new AreaAuxiliaryNode(
                static fn(PrintContext $context): string => $node->printInnerContent($context)
            );
        }

        return $node;
    }

    /**
     * Converts raw argument text into an expression - unquoted values are wrapped
     * in double quotes, so that variables inside them are interpolated.
     */
    private static function parseValue(string $text, Tag $tag): ExpressionNode
    {
        $text = trim($text);

        $code = $text !== '' && ($text[0] === '"' || $text[0] === "'")
            ? $text
            : '"' . addcslashes($text, '"\\') . '"';

        try {
            // position must not be passed - lexer would use its offset as an offset in $code
            return (new TagParser((new TagLexer())->tokenize($code)))->parseExpression();
        } catch (CompileException $e) {
            throw new CompileException($e->getMessage(), $tag->position);
        }
    }

    public function print(PrintContext $context): string
    {
        $setup = $context->format(
            '$_inline_namespace = (string) %node; $_inline_locale = (string) %node; '
            . '$_inline_name = (string) %node; ',
            $this->namespace,
            $this->locale,
            $this->name,
        );

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
        yield $this->namespace;
        yield $this->locale;
        yield $this->name;
        yield $this->modifier;
        if ($this->content !== null) {
            yield $this->content;
        }
    }
}
