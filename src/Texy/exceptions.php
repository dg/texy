<?php

/**
 * This file is part of the Texy! (https://texy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

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
