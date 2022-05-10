<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy\Bridges\Latte;

use Latte;
use Latte\Compiler\NodeHelpers;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;


/**
 * {texy} ... {/texy}
 */
class TexyNode extends StatementNode
{
	public AreaNode $content;
	public ArrayNode $args;


	/** @return \Generator<int, ?array, array{AreaNode, ?Tag}, static> */
	public static function create(Tag $tag, TemplateParser $parser, callable $processor): \Generator
	{
		$node = new static;
		$node->args = $tag->parser->parseArguments();

		$saved = $parser->getContentType();
		$parser->setContentType(Latte\ContentType::Text);
		[$node->content] = yield;
		$parser->setContentType($saved);

		$text = NodeHelpers::toText($node->content);
		if ($text !== null) {
			try {
				$text = $processor($text, ...NodeHelpers::toValue($node->args));
				return new Latte\Compiler\Nodes\TextNode($text);
			} catch (\Throwable) {
			}
		}

		return $node;
	}


	public function print(PrintContext $context): string
	{
		$context->beginEscape()->enterContentType(Latte\ContentType::Text);
		$res = $context->format(
			<<<'XX'
				ob_start(fn() => '') %line;
				try {
					%node
				} finally {
					$ʟ_tmp = ob_get_clean();
				}
				echo ($this->global->texy)($ʟ_tmp, ...%node);


				XX,
			$this->position,
			$this->content,
			$this->args,
		);
		$context->restoreEscape();
		return $res;
	}


	public function &getIterator(): \Generator
	{
		yield $this->content;
	}
}
