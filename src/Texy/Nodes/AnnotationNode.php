<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy\Range;


/**
 * Annotation.
 * NATO((North Atlantic Treaty Organisation))
 */
class AnnotationNode extends InlineNode
{
	public function __construct(
		public string $text,
		public string $annotation,
		public ?Range $range = null,
	) {
	}
}
