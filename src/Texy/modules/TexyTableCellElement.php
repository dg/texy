<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */


/**
 * Table cell TD / TH.
 */
class TexyTableCellElement extends TexyHtml
{
	/** @var int */
	public $colSpan = 1;

	/** @var int */
	public $rowSpan = 1;

	/** @var string */
	public $text;

}
