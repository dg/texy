<?php

/**
 * Texy! is human-readable text to HTML converter (http://texy.info)
 *
 * Copyright (c) 2004, 2014 David Grudl (http://davidgrudl.com)
 */


// Check PHP configuration
if (version_compare(PHP_VERSION, '5.3.0') < 0) {
	throw new Exception('Texy requires PHP 5.3.0 or newer.');
}

if (extension_loaded('mbstring')) {
	if (mb_get_info('func_overload') & 2 && substr(mb_get_info('internal_encoding'), 0, 1) === 'U') { // U??
		mb_internal_encoding('pass');
		trigger_error("Texy: mb_internal_encoding changed to 'pass'", E_USER_WARNING);
	}
}


// load libraries
require_once __DIR__ . '/Texy/Patterns.php';
require_once __DIR__ . '/Texy/Object.php';
require_once __DIR__ . '/Texy/HtmlElement.php';
require_once __DIR__ . '/Texy/Modifier.php';
require_once __DIR__ . '/Texy/Module.php';
require_once __DIR__ . '/Texy/Parser.php';
require_once __DIR__ . '/Texy/Configurator.php';
require_once __DIR__ . '/Texy/HandlerInvocation.php';
require_once __DIR__ . '/Texy/Regexp.php';
require_once __DIR__ . '/Texy/RegexpException.php';
require_once __DIR__ . '/Texy/Texy.php';
require_once __DIR__ . '/Texy/Modules/ParagraphModule.php';
require_once __DIR__ . '/Texy/Modules/BlockModule.php';
require_once __DIR__ . '/Texy/Modules/HeadingModule.php';
require_once __DIR__ . '/Texy/Modules/HorizLineModule.php';
require_once __DIR__ . '/Texy/Modules/HtmlModule.php';
require_once __DIR__ . '/Texy/Modules/FigureModule.php';
require_once __DIR__ . '/Texy/Modules/ImageModule.php';
require_once __DIR__ . '/Texy/Modules/LinkModule.php';
require_once __DIR__ . '/Texy/Modules/ListModule.php';
require_once __DIR__ . '/Texy/Modules/LongWordsModule.php';
require_once __DIR__ . '/Texy/Modules/PhraseModule.php';
require_once __DIR__ . '/Texy/Modules/BlockQuoteModule.php';
require_once __DIR__ . '/Texy/Modules/ScriptModule.php';
require_once __DIR__ . '/Texy/Modules/EmoticonModule.php';
require_once __DIR__ . '/Texy/Modules/TableModule.php';
require_once __DIR__ . '/Texy/Modules/TypographyModule.php';
require_once __DIR__ . '/Texy/Modules/HtmlOutputModule.php';

class_alias('Texy\Texy', 'Texy');
