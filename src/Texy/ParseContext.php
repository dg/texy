<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;

use Texy\Nodes\ContentNode;


/**
 * Context for parsing.
 * Provides methods for recursive parsing and access to block parser.
 */
class ParseContext
{
	public function __construct(
		private InlineParser $inlineParser,
		private BlockParser $blockParser,
	) {
	}


	/**
	 * Parse text as inline content.
	 */
	public function parseInline(string $text): ContentNode
	{
		return $this->inlineParser->parse($this, $text);
	}


	/**
	 * Parse text as block content.
	 * Creates a cloned parser for isolated recursive parsing.
	 */
	public function parseBlock(string $text): ContentNode
	{
		$clonedParser = clone $this->blockParser;
		$newContext = new self($this->inlineParser, $clonedParser);
		return $clonedParser->parse($newContext, $text);
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
