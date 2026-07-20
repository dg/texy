<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy\Modifier;


/**
 * Image definition.
 * [*ref*]: image.jpg .(title)[class]{style}
 */
class ImageDefinitionNode extends BlockNode
{
	public function __construct(
		public string $reference,
		public ?string $url = null,
		public ?int $width = null,
		public ?int $height = null,
		public ?Modifier $modifier = null,
	) {
	}
}
