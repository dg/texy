<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Output\Html;


/**
 * Piece of ready-made HTML in the rendered tree: it bypasses text escaping
 * and is tokenized by the well-forming engine as-is.
 */
final class Raw
{
	public function __construct(
		public string $html,
	) {
	}
}
