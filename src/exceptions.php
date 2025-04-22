<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


/**
 * Processing failed on the text; an error in the use of the library is a \LogicException.
 */
class Exception extends \Exception
{
}


/**
 * Matching the text exhausted a limit of PCRE; a pattern that does not compile is a \LogicException.
 */
class RegexpException extends Exception
{
}


/**
 * The argument does not match the expected value.
 */
class InvalidArgumentException extends \InvalidArgumentException
{
}
