<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


/**
 * Texy parser base class.
 */
class Parser
{
	protected Texy $texy;
	protected HtmlElement $element;


	public function getTexy(): Texy
	{
		return $this->texy;
	}
}
