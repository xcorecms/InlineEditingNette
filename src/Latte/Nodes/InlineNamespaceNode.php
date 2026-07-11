<?php

declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Latte\Nodes;

use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

/**
 * {inlineNamespace name}...{/inlineNamespace}
 *
 * @author Jakub Janata <jakubjanata@gmail.com>
 */
final class InlineNamespaceNode extends StatementNode
{
    public ExpressionNode $namespace;

    public AreaNode $content;

    /**
     * @return \Generator<int, ?array<string>, array{AreaNode, ?Tag}, static>
     */
    public static function create(Tag $tag): \Generator
    {
        $tag->expectArguments();
        $node = new static();
        $tag->node = $node;
        $node->namespace = $tag->parser->parseUnquotedStringOrExpression();

        [$node->content] = yield;

        return $node;
    }

    public function print(PrintContext $context): string
    {
        return $context->format(
            '$inlineNamespaceStack[] = %node %line; try { %node } finally { array_pop($inlineNamespaceStack); } ',
            $this->namespace,
            $this->position,
            $this->content,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->namespace;
        yield $this->content;
    }
}
