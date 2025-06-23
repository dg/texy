<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy;
use Texy\Range;


/**
 * Preformatted text. Written /--pre it may hold HTML tags and entities, a bare /-- shows it as written.
 *
 * /--pre
 * Text
 * \--
 */
class PreformattedNode extends VerbatimBlockNode
{
	public function __construct(
		string $code,
		/** the code parsed for its HTML tags and entities, or the code itself for a bare /-- */
		public ContentNode $content = new ContentNode,
		?Texy\Modifier $modifier = null,
		?Range $range = null,
		?Texy\Parsing\OffsetMap $codeMap = null,
		/** name of the syntax it is written in, Syntax::BlockPre or Syntax::BlockDefault for a bare /-- */
		public ?string $syntax = null,
	) {
		parent::__construct($code, $modifier, $range, $codeMap);
	}


	public function &getChildren(): \Generator
	{
		yield $this->content;
	}
}
