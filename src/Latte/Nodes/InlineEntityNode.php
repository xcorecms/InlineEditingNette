<?php

declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Latte\Nodes;

use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\AuxiliaryNode;
use Latte\Compiler\Nodes\FragmentNode;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\Php\ModifierNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

/**
 * <div n:inlineEntity="$entity, 'property'">, <div n:inlineField="property"> and their *Html variants
 *
 * @author Jakub Janata <jakubjanata@gmail.com>
 */
final class InlineEntityNode extends StatementNode
{
    public ?AreaNode $content = null;

    public ModifierNode $modifier;

    /** entity + property arguments ({inlineEntity} and {inlineEntityHtml}) */
    public ?ArrayNode $args = null;

    /** property name, entity is taken from $inlineEntityStack ({inlineField} and {inlineFieldHtml}) */
    public ?ExpressionNode $property = null;

    private string $type;

    private bool $isNAttribute = false;

    /**
     * @return \Generator<int, ?array<string>, array{AreaNode, ?Tag}, static>
     */
    public static function create(Tag $tag, bool $fromStack, string $type): \Generator
    {
        $tag->expectArguments();
        $node = new static();
        $tag->node = $node;
        $node->type = $type;

        if ($fromStack) {
            $node->property = $tag->parser->parseUnquotedStringOrExpression();
        } else {
            $node->args = $tag->parser->parseArguments();
        }

        $node->modifier = $tag->parser->parseModifier();

        [$node->content] = yield;

        if ($tag->isNAttribute() && $tag->htmlElement !== null) {
            $node->isNAttribute = true;
            $element = $tag->htmlElement;
            $element->attributes ??= new FragmentNode;
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
        $setup = $this->args !== null
            ? $context->format('[$_inline_entity, $_inline_property] = [%args]; ', $this->args)
            : $context->format(
                '$_inline_entity = end($inlineEntityStack); $_inline_property = %node; ',
                $this->property,
            );

        $setup .= '$_inline_class = get_class($_inline_entity); '
            . '$_inline_id = $_inline_entity->id; '
            . '$_inline_content = $_inline_entity->{$_inline_property}; ';

        return $setup . ($this->isNAttribute && $this->content !== null
            ? $this->content->print($context)
            : $this->printInnerContent($context));
    }

    private function printAttributes(PrintContext $context): string
    {
        return $context->format(
            <<<'XX'
                if ($this->global->inlinePermissionChecker->isEntityEditationAllowed($_inline_entity)) {
                    echo ' id="inline_',
                        LR\Filters::escapeHtmlAttr($_inline_class . '_' . $_inline_id . '_' . $_inline_property),
                        '"',
                        ' data-inline-type="', %dump, '"',
                        ' data-inline-entity="', LR\Filters::escapeHtmlAttr($_inline_class), '"',
                        ' data-inline-id="', LR\Filters::escapeHtmlAttr($_inline_id), '"',
                        ' data-inline-property="', LR\Filters::escapeHtmlAttr($_inline_property), '"';
                }

                XX,
            $this->type,
        );
    }

    private function printInnerContent(PrintContext $context): string
    {
        return $context->format(
            'echo %modify($_inline_content) %line;',
            $this->modifier,
            $this->position,
        );
    }

    public function &getIterator(): \Generator
    {
        if ($this->args !== null) {
            yield $this->args;
        }
        if ($this->property !== null) {
            yield $this->property;
        }
        yield $this->modifier;
        if ($this->content !== null) {
            yield $this->content;
        }
    }
}
