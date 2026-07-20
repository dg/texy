<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy\Range;


/**
 * Paired HTML element from passthrough input with real children.
 * <b>content</b>
 *
 * Created by HtmlPairingPass from a matching HtmlTagNode open/close pair;
 * unpairable tags stay as standalone HtmlTagNode.
 */
class HtmlElementNode extends InlineNode
{
	/**
	 * @param array<string, string|bool|null> $attributes HTML attributes of the opening tag
	 * @param ?HtmlTagNode $closingTag original closing tag (case/attributes may differ), for faithful escaping
	 */
	public function __construct(
		public string $name,
		public array $attributes = [],
		public ContentNode $content = new ContentNode,
		public ?HtmlTagNode $closingTag = null,
		public ?Range $range = null,
	) {
	}


	public function &getChildren(): \Generator
	{
		yield $this->content;
	}
}
