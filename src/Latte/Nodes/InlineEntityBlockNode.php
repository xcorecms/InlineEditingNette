<?php

declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Latte\Nodes;

use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

/**
 * {inlineEntityBlock $entity}...{/inlineEntityBlock}
 *
 * @author Jakub Janata <jakubjanata@gmail.com>
 */
final class InlineEntityBlockNode extends StatementNode
{
    public ExpressionNode $entity;

    public AreaNode $content;

    /**
     * @return \Generator<int, ?array<string>, array{AreaNode, ?Tag}, static>
     */
    public static function create(Tag $tag): \Generator
    {
        $tag->expectArguments();
        $node = new static();
        $tag->node = $node;
        $node->entity = $tag->parser->parseExpression();

        [$node->content] = yield;

        return $node;
    }

    public function print(PrintContext $context): string
    {
        return $context->format(
            '$inlineEntityStack[] = %node %line; try { %node } finally { array_pop($inlineEntityStack); } ',
            $this->entity,
            $this->position,
            $this->content,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->entity;
        yield $this->content;
    }
}
