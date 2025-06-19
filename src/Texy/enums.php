<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


/**
 * When a pass runs. Syntax passes finish recognizing the source and run in parse(),
 * the others apply its meaning and run in transform(), the Finalize ones last.
 */
enum Stage
{
	case Syntax;
	case Transform;
	case Finalize;
}
