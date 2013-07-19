<?php

/**
 * Texy! is human-readable text to HTML converter (http://texy.info)
 *
 * Copyright (c) 2004, 2012 David Grudl (http://davidgrudl.com)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */


/**
 * Check PHP configuration.
 */
if (extension_loaded('mbstring')) {
	if (mb_get_info('func_overload') & 2 && substr(mb_get_info('internal_encoding'), 0, 1) === 'U') { // U??
		mb_internal_encoding('pass');
		trigger_error("Texy: mb_internal_encoding changed to 'pass'", E_USER_WARNING);
	}
}

if (ini_get('zend.ze1_compatibility_mode') % 256 ||
	preg_match('#on$|true$|yes$#iA', ini_get('zend.ze1_compatibility_mode'))
) {
	throw new RuntimeException("Texy cannot run with zend.ze1_compatibility_mode enabled.");
}


// Texy! libraries
require_once dirname(__FILE__) . '/Texy/TexyPatterns.php';
require_once dirname(__FILE__) . '/Texy/TexyObject.php';
require_once dirname(__FILE__) . '/Texy/TexyHtml.php';
require_once dirname(__FILE__) . '/Texy/TexyModifier.php';
require_once dirname(__FILE__) . '/Texy/TexyModule.php';
require_once dirname(__FILE__) . '/Texy/TexyParser.php';
require_once dirname(__FILE__) . '/Texy/TexyUtf.php';
require_once dirname(__FILE__) . '/Texy/TexyConfigurator.php';
require_once dirname(__FILE__) . '/Texy/TexyHandlerInvocation.php';
require_once dirname(__FILE__) . '/Texy/Texy.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyParagraphModule.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyBlockModule.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyHeadingModule.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyHorizLineModule.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyHtmlModule.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyFigureModule.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyImageModule.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyLinkModule.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyListModule.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyLongWordsModule.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyPhraseModule.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyBlockQuoteModule.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyScriptModule.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyEmoticonModule.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyTableModule.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyTypographyModule.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyHtmlOutputModule.php';


/**
 * Compatibility with PHP < 5.1.
 */
if (!class_exists('LogicException', FALSE)) {
	class LogicException extends Exception {}
}

if (!class_exists('InvalidArgumentException', FALSE)) {
	class InvalidArgumentException extends LogicException {}
}

if (!class_exists('RuntimeException', FALSE)) {
	class RuntimeException extends Exception {}
}

if (!class_exists('UnexpectedValueException', FALSE)) {
	class UnexpectedValueException extends RuntimeException {}
}
