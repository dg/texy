<?php

/**
 * Texy! is human-readable text to HTML converter (http://texy.info)
 *
 * Copyright (c) 2004, 2014 David Grudl (http://davidgrudl.com)
 */


// Check PHP configuration
if (version_compare(PHP_VERSION, '5.2.0') < 0) {
	throw new Exception('Texy requires PHP 5.2.0 or newer.');
} elseif (ini_get('zend.ze1_compatibility_mode') % 256 ||
	preg_match('#on$|true$|yes$#iA', ini_get('zend.ze1_compatibility_mode'))
) {
	throw new Exception('Texy cannot run with zend.ze1_compatibility_mode enabled.');
}


// load libraries
require_once dirname(__FILE__) . '/Texy/TexyPatterns.php';
require_once dirname(__FILE__) . '/Texy/TexyObject.php';
require_once dirname(__FILE__) . '/Texy/TexyHtml.php';
require_once dirname(__FILE__) . '/Texy/TexyModifier.php';
require_once dirname(__FILE__) . '/Texy/TexyModule.php';
require_once dirname(__FILE__) . '/Texy/TexyParser.php';
require_once dirname(__FILE__) . '/Texy/TexyBlockParser.php';
require_once dirname(__FILE__) . '/Texy/TexyLineParser.php';
require_once dirname(__FILE__) . '/Texy/TexyUtf.php';
require_once dirname(__FILE__) . '/Texy/TexyConfigurator.php';
require_once dirname(__FILE__) . '/Texy/TexyHandlerInvocation.php';
require_once dirname(__FILE__) . '/Texy/TexyRegexp.php';
require_once dirname(__FILE__) . '/Texy/Texy.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyImage.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyLink.php';
require_once dirname(__FILE__) . '/Texy/modules/TexyTableCellElement.php';
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
