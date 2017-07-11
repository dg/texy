<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;


/**
 * Table cell TD / TH.
 */
class TableCellElement extends Texy\HtmlElement
{
	/** @var int */
	public $colSpan = 1;

	/** @var int */
	public $rowSpan = 1;

	/** @var string */
	public $text;
}
