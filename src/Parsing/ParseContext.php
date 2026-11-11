<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Parsing;

use Texy\Nodes\ContentNode;


/**
 * Context for parsing.
 * Provides methods for recursive parsing and access to block parser.
 */
class ParseContext
{
	/** how deep blocks nest; a block every level reads its whole content again, so the cost grows with the square of the depth */
	public const MaxDepth = 100;


	public function __construct(
		private InlineParser $inlineParser,
		private BlockParser $blockParser,
		/** @var array<string, bool>  syntax name => enabled */
		public readonly array $allowedSyntaxes = [],
		/** @var ?\Closure(string, int): ContentNode  takes the inline content instead of the parser, in a language parsing it later */
		private ?\Closure $inlineDeferral = null,
		private int $depth = 0,
	) {
	}


	/**
	 * Parse text as inline content.
	 */
	public function parseInline(string $text, int $baseOffset = 0): ContentNode
	{
		return $this->inlineDeferral === null
			? $this->inlineParser->parse($this, $text, $baseOffset)
			: ($this->inlineDeferral)($text, $baseOffset);
	}


	/**
	 * Parse text as block content.
	 * Creates a cloned parser for isolated recursive parsing. Below MaxDepth no block syntax
	 * is read any more and the content is the text between them.
	 */
	public function parseBlock(string $text, int $baseOffset = 0): ContentNode
	{
		$clonedParser = $this->depth < self::MaxDepth
			? clone $this->blockParser
			: $this->blockParser->withoutPatterns();
		$newContext = new self(
			$this->inlineParser,
			$clonedParser,
			$this->allowedSyntaxes,
			$this->inlineDeferral,
			$this->depth + 1,
		);
		return $clonedParser->parse($newContext, $text, $baseOffset);
	}


	/**
	 * Returns current block parser for navigation (next, moveBackward).
	 */
	public function getBlockParser(): BlockParser
	{
		return $this->blockParser;
	}


	/**
	 * Returns inline parser (for creating filtered variants via withPatterns).
	 */
	public function getInlineParser(): InlineParser
	{
		return $this->inlineParser;
	}
}
