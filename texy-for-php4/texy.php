<?php

/**
 * Texy! - web text markup-language (for PHP 4)
 * --------------------------------------------
 *
 * Copyright (c) 2004, 2007 David Grudl aka -dgx- (http://www.dgx.cz)
 *
 * This source file is subject to the GNU GPL license that is bundled
 * with this package in the file license.txt.
 *
 * For more information please see http://texy.info/
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004, 2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @version    2.0 BETA 2 (Revision: $WCREV$, Date: $WCDATE$)
 * @category   Text
 * @package    Texy
 * @link       http://texy.info/
 */



/** @version $Revision$ $Date$ */


define('TEXY_VERSION',  '2.0 FOR PHP4 BETA 2 (Revision: $WCREV$, Date: $WCDATE$)');

// suppress E_STRICT in PHP 5
if (PHP_VERSION >= 5) {
    error_reporting(error_reporting() & ~E_STRICT);
}

require_once __FILE__ . '/../libs/NObject4.php';
require_once __FILE__ . '/../libs/Texy.php';
require_once __FILE__ . '/../libs/RegExp.Patterns.php';
require_once __FILE__ . '/../libs/TexyHtml.php';
require_once __FILE__ . '/../libs/TexyModifier.php';
require_once __FILE__ . '/../libs/TexyModule.php';
require_once __FILE__ . '/../libs/TexyParser.php';
require_once __FILE__ . '/../libs/TexyUtf.php';
require_once __FILE__ . '/../libs/TexyConfigurator.php';
require_once __FILE__ . '/../libs/TexyHandlerInvocation.php';
require_once __FILE__ . '/../modules/TexyParagraphModule.php';
require_once __FILE__ . '/../modules/TexyBlockModule.php';
require_once __FILE__ . '/../modules/TexyHeadingModule.php';
require_once __FILE__ . '/../modules/TexyHorizLineModule.php';
require_once __FILE__ . '/../modules/TexyHtmlModule.php';
require_once __FILE__ . '/../modules/TexyFigureModule.php';
require_once __FILE__ . '/../modules/TexyImageModule.php';
require_once __FILE__ . '/../modules/TexyLinkModule.php';
require_once __FILE__ . '/../modules/TexyListModule.php';
require_once __FILE__ . '/../modules/TexyLongWordsModule.php';
require_once __FILE__ . '/../modules/TexyPhraseModule.php';
require_once __FILE__ . '/../modules/TexyBlockQuoteModule.php';
require_once __FILE__ . '/../modules/TexyScriptModule.php';
require_once __FILE__ . '/../modules/TexyEmoticonModule.php';
require_once __FILE__ . '/../modules/TexyTableModule.php';
require_once __FILE__ . '/../modules/TexyTypographyModule.php';
require_once __FILE__ . '/../modules/TexyHtmlOutputModule.php';




/**
 * PHP requirements checker
 */
if (version_compare(PHP_VERSION , '4.3.3', '<')) {
    die('Texy needs PHP 4.3.3 or newer.');
}

if (function_exists('mb_get_info')) {
    if (mb_get_info('func_overload') & 2 && substr(mb_get_info('internal_encoding'), 0, 1) === 'U') { // U??
        mb_internal_encoding('pass');
        trigger_error("Texy: mb_internal_encoding changed to 'pass'", E_USER_WARNING);
    }
}
