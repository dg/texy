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


/**
 * How the levels of headings are computed.
 */
enum HeadingBalancing
{
	/** from the order of heading styles in the document */
	case Dynamic;

	/** from TexyConfig::$headingLevels */
	case Fixed;
}


/**
 * What a callback of NodeTraverser asks for, when it returns no replacement node.
 */
enum TraverseAction
{
	/** the children of the node are not visited */
	case SkipChildren;

	/** the traversal ends */
	case Stop;

	/** the node is removed; only a node held in a list can be */
	case Remove;
}
