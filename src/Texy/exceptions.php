<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


class Exception extends \Exception
{
}


/**
 * Regular expression pattern or execution failed.
 */
class RegexpException extends Exception
{
}
