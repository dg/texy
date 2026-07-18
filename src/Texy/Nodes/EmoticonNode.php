<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy\Range;


/**
 * Emoticon.
 * :-)
 */
class EmoticonNode extends InlineNode
{
	/** Emoji/text the emoticon resolves to; filled in the transform phase so generators need not reach into modules */
	public ?string $resolved = null;


	public function __construct(
		public string $emoticon,
		public ?Range $range = null,
	) {
	}
}
