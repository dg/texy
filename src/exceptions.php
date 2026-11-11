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
 * The input is not valid UTF-8 text.
 */
class InvalidInputException extends Exception
{
}


/**
 * Regular expression pattern or execution failed.
 */
class RegexpException extends Exception
{
}


/**
 * A replacement does not fit the source: it overlaps another one or finds other text than expected.
 */
class RewriteException extends Exception
{
}


/**
 * The argument does not match the expected value.
 */
class InvalidArgumentException extends \InvalidArgumentException
{
}
