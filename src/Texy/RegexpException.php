<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Texy;


class RegexpException extends \Exception
{
	private static $messages = array(
		PREG_INTERNAL_ERROR => 'Internal error',
		PREG_BACKTRACK_LIMIT_ERROR => 'Backtrack limit was exhausted',
		PREG_RECURSION_LIMIT_ERROR => 'Recursion limit was exhausted',
		PREG_BAD_UTF8_ERROR => 'Malformed UTF-8 data',
		5 => 'Offset didn\'t correspond to the begin of a valid UTF-8 code point', // PREG_BAD_UTF8_OFFSET_ERROR
	);


	public function __construct($message = NULL, $code = NULL, $pattern = NULL)
	{
		if ($message === NULL && isset(self::$messages[$code])) {
			$message = self::$messages[$code];
		}
		if ($pattern !== NULL) {
			$message .= " (pattern: $pattern)";
		}
		parent::__construct($message, $code);
	}

}
