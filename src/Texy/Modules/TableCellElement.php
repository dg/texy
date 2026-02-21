<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Modules;

use Texy;


/**
 * Table cell TD / TH.
 */
class TableCellElement extends Texy\HtmlElement
{
	public int $colSpan = 1;
	public int $rowSpan = 1;
	public ?string $text = null;
}
