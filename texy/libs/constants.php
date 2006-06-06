<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://www.texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 * @package    Texy
 * @category   Text
 * @version    $Revision$ $Date$
 */


// security - include texy.php, not this file
if (!defined('TEXY')) die();



// MODIFIERS - ALIGN
define('TEXY_HALIGN_LEFT',      'left');
define('TEXY_HALIGN_RIGHT',     'right');
define('TEXY_HALIGN_CENTER',    'center');
define('TEXY_HALIGN_JUSTIFY',   'justify');
define('TEXY_VALIGN_TOP',       'top');
define('TEXY_VALIGN_MIDDLE',    'middle');
define('TEXY_VALIGN_BOTTOM',    'bottom');


define('TEXY_URL_ABSOLUTE',     1);
define('TEXY_URL_RELATIVE',     2);
define('TEXY_URL_EMAIL',        4);
define('TEXY_URL_IMAGE',        8);


// TEXY ELEMENT'S CONTENT TYPE
define('TEXY_CONTENT_NONE',    1);
define('TEXY_CONTENT_TEXTUAL', 2);
define('TEXY_CONTENT_BLOCK',   3);


// HTML ELEMENT CLASIFICATION
// notice: I use a little trick - isset($array[$item]) is much faster than in_array($item, $array)

$GLOBALS['TexyHTML::$block'] = array_flip(array(
    'address','blockquote','caption','col','colgroup','dd','div','dl','dt','fieldset','form','h1','h2','h3','h4','h5','h6','hr','iframe','legend','li','object','ol','p','param','pre','table','tbody','td','tfoot','th','thead','tr','ul',/*'embed',*/
));

$GLOBALS['TexyHTML::$inline'] = array_flip(array(
    'a','abbr','acronym','area','b','big','br','button','cite','code','del','dfn','em','i','img','input','ins','kbd','label','map','noscript','optgroup','option','q','samp','script','select','small','span','strong','sub','sup','textarea','tt','var',
));

$GLOBALS['TexyHTML::$empty'] = array_flip(array(
    'img','hr','br','input','meta','area','base','col','link','param',
));
/*
$GLOBALS['TexyHTML::$meta'] = array_flip(array(
    'html','head','body','base','meta','link','title',
));
*/
$GLOBALS['TexyHTML::$valid'] = array_merge($GLOBALS['TexyHTML::$block'], $GLOBALS['TexyHTML::$inline']);

$GLOBALS['TexyHTML::$accepted_attrs'] = array_flip(array(
    'abbr','accesskey','align','alt','archive','axis','bgcolor','cellpadding','cellspacing','char','charoff','charset','cite','classid','codebase','codetype','colspan','compact','coords','data','datetime','declare','dir','face','frame','headers','href','hreflang','hspace','ismap','lang','longdesc','name','noshade','nowrap','onblur','onclick','ondblclick','onkeydown','onkeypress','onkeyup','onmousedown','onmousemove','onmouseout','onmouseover','onmouseup','rel','rev','rowspan','rules','scope','shape','size','span','src','standby','start','summary','tabindex','target','title','type','usemap','valign','value','vspace',
));


define('TEXY_EMPTY',    '/');


// CONFIGURATION DIRECTIVES
define('TEXY_ALL',   TRUE);
define('TEXY_NONE',  FALSE);



// REGULAR EXPRESSIONS

//     international characters 'A-Za-z\x86-\xff'
//     unicode                  'A-Za-z\x86-\x{ffff}'
//     numbers                  0-9
//     spaces                   \n\r\t\x32
//     control                  \x00 - \x31  (without spaces)
//     others                   !"#$%&'()*+,-./:;<=>?@[\]^_`{|}~


// character classes
define('TEXY_CHAR',             'A-Za-z\x86-\xff');       // INTERNATIONAL CHAR - USE INSTEAD OF \w
define('TEXY_CHAR_UTF',         'A-Za-z\x86-\x{ffff}');
define('TEXY_NEWLINE',          "\n");
// hashing meta-charakters
define('TEXY_HASH',             "\x15-\x1F");       // ANY HASH CHAR
define('TEXY_HASH_SPACES',      "\x15-\x18");       // HASHED SPACE
define('TEXY_HASH_NC',          "\x19\x1B-\x1F");   // HASHED TAG or ELEMENT (without content)
define('TEXY_HASH_WC',          "\x1A-\x1F");       // HASHED TAG or ELEMENT (with content)


// links
define('TEXY_PATTERN_LINK_REF',        '\[[^\[\]\*\n'.TEXY_HASH.']+\]');    // reference  [refName]
define('TEXY_PATTERN_LINK_IMAGE',      '\[\*[^\n'.TEXY_HASH.']+\*\]');      // [* ... *]
define('TEXY_PATTERN_LINK_URL',        '(?:\[[^\]\n]+\]|(?!\[)[^\s'.TEXY_HASH.']*?[^:);,.!?\s'.TEXY_HASH.'])'); // any url (nekonèí :).,!?
define('TEXY_PATTERN_LINK',            '(?::('.TEXY_PATTERN_LINK_URL.'))');    // any link
define('TEXY_PATTERN_LINK_N',          '(?::('.TEXY_PATTERN_LINK_URL.'|:))');  // any link (also unstated)
define('TEXY_PATTERN_EMAIL',           '[a-z0-9.+_-]+@[a-z0-9.+_-]{2,}\.[a-z]{2,}');    // name@exaple.com


// modifier .(title)[class]{style}
define('TEXY_PATTERN_MODIFIER', '(?:\ *(?<= |^)\.(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\})(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\})??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\})??)');

// modifier .(title)[class]{style}<>
define('TEXY_PATTERN_MODIFIER_H', '(?:\ *(?<= |^)\.(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))??)');

// modifier .(title)[class]{style}<>^
define('TEXY_PATTERN_MODIFIER_HV', '(?:\ *(?<= |^)\.(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??)');


/** modifiers patterns generator:

$TITLE =  '\([^\n\)]+\)';      // (...)
$CLASS =  '\[[^\n\]]+\]';      // [...]
$STYLE =  '\{[^\n\}]+\}';      // {...}
$HALIGN = '(?:<>|>|=|<)';      //  <  >  =  <>
$VALIGN = '(?:\^|\-|\_)';      //  ~ - _

// .(title)[class]{style}
define('TEXY_PATTERN_MODIFIER',
         "(?:\\ *(?<= |^)\\.".
         "($TITLE|$CLASS|$STYLE)".
         "($TITLE|$CLASS|$STYLE)??".
         "($TITLE|$CLASS|$STYLE)??)");


// .(title)[class]{style}<>
define('TEXY_PATTERN_MODIFIER_H',
         "(?:\\ *(?<= |^)\\.".
         "($TITLE|$CLASS|$STYLE|$HALIGN)".
         "($TITLE|$CLASS|$STYLE|$HALIGN)??".
         "($TITLE|$CLASS|$STYLE|$HALIGN)??".
         "($TITLE|$CLASS|$STYLE|$HALIGN)??)");


// .(title)[class]{style}<>^
define('TEXY_PATTERN_MODIFIER_HV',
         "(?:\\ *(?<= |^)\\.".
         "($TITLE|$CLASS|$STYLE|$HALIGN|$VALIGN)".
         "($TITLE|$CLASS|$STYLE|$HALIGN|$VALIGN)??".
         "($TITLE|$CLASS|$STYLE|$HALIGN|$VALIGN)??".
         "($TITLE|$CLASS|$STYLE|$HALIGN|$VALIGN)??".
         "($TITLE|$CLASS|$STYLE|$HALIGN|$VALIGN)??)");

*/
?>