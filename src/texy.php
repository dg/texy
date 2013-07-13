<?php

/**
 * Texy! is human-readable text to HTML converter (http://texy.info)
 *
 * Copyright (c) 2004, 2014 David Grudl (http://davidgrudl.com)
 */


// Check PHP configuration
if (version_compare(PHP_VERSION, '5.3.0') < 0) {
	throw new Exception('Texy requires PHP 5.3.0 or newer.');

} elseif (PCRE_VERSION == 8.34 && PHP_VERSION_ID < 50513) {
	trigger_error('Texy: PCRE 8.34 is not supported due to bug #1451', E_USER_WARNING);
}

if (extension_loaded('mbstring')) {
	if (mb_get_info('func_overload') & 2 && substr(mb_get_info('internal_encoding'), 0, 1) === 'U') { // U??
		mb_internal_encoding('pass');
		trigger_error("Texy: mb_internal_encoding changed to 'pass'", E_USER_WARNING);
	}
}


// load libraries
require_once __DIR__ . '/Texy/TexyPatterns.php';
require_once __DIR__ . '/Texy/TexyObject.php';
require_once __DIR__ . '/Texy/TexyHtml.php';
require_once __DIR__ . '/Texy/TexyModifier.php';
require_once __DIR__ . '/Texy/TexyModule.php';
require_once __DIR__ . '/Texy/TexyParser.php';
require_once __DIR__ . '/Texy/TexyConfigurator.php';
require_once __DIR__ . '/Texy/TexyHandlerInvocation.php';
require_once __DIR__ . '/Texy/TexyRegexp.php';
require_once __DIR__ . '/Texy/TexyRegexpException.php';
require_once __DIR__ . '/Texy/Texy.php';
require_once __DIR__ . '/Texy/modules/TexyParagraphModule.php';
require_once __DIR__ . '/Texy/modules/TexyBlockModule.php';
require_once __DIR__ . '/Texy/modules/TexyHeadingModule.php';
require_once __DIR__ . '/Texy/modules/TexyHorizLineModule.php';
require_once __DIR__ . '/Texy/modules/TexyHtmlModule.php';
require_once __DIR__ . '/Texy/modules/TexyFigureModule.php';
require_once __DIR__ . '/Texy/modules/TexyImageModule.php';
require_once __DIR__ . '/Texy/modules/TexyLinkModule.php';
require_once __DIR__ . '/Texy/modules/TexyListModule.php';
require_once __DIR__ . '/Texy/modules/TexyLongWordsModule.php';
require_once __DIR__ . '/Texy/modules/TexyPhraseModule.php';
require_once __DIR__ . '/Texy/modules/TexyBlockQuoteModule.php';
require_once __DIR__ . '/Texy/modules/TexyScriptModule.php';
require_once __DIR__ . '/Texy/modules/TexyEmoticonModule.php';
require_once __DIR__ . '/Texy/modules/TexyTableModule.php';
require_once __DIR__ . '/Texy/modules/TexyTypographyModule.php';
require_once __DIR__ . '/Texy/modules/TexyHtmlOutputModule.php';
