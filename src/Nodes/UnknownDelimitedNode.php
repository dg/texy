<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Nodes;

use Texy\Parsing\OffsetMap;
use Texy\Range;


/**
 * Delimited block of a type Texy does not know, its whole source as written.
 *
 * /--foo
 * Text
 * \--
 */
class UnknownDelimitedNode extends VerbatimBlockNode
{
	public function __construct(
		string $code,
		/** lower-case */
		public string $type,
		?Range $range = null,
		?OffsetMap $codeMap = null,
	) {
		parent::__construct($code, null, $range, $codeMap);
	}
}
