<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    $Revision$ $Date$
 */


// security - include texy.php, not this file
if (!defined('TEXY')) die();



// REGULAR EXPRESSIONS

//     international characters 'A-Za-z\x86-\xff'
//     unicode                  'A-Za-z\x86-\x{ffff}'
//     numbers                  0-9
//     spaces                   \n\r\t\x32
//     control                  \x00 - \x31  (without spaces)
//     others                   !"#$%&'()*+,-./:;<=>?@[\]^_`{|}~


// character classes
define('TEXY_CHAR',            'A-Za-z\x86-\x{ffff}');

// hashing meta-charakters
define('TEXY_HASH',            "\x01-\x04\x14-\x1F");       // ANY HASH CHAR
define('TEXY_HASH_SPACES',     "\x01-\x04");       // HASHED SPACE
define('TEXY_HASH_N',          "\x14\x18-\x1F");    // hashed CONTENT_NONE
define('TEXY_HASH_I',          "\x15\x18-\x1F");    // hashed CONTENT_INLINE 
define('TEXY_HASH_T',          "\x16\x18-\x1F");    // hashed CONTENT_TEXTUAL
define('TEXY_HASH_B',          "\x17\x18-\x1F");    // hashed CONTENT_BLOCK


// modifier .(title)[class]{style}
define('TEXY_MODIFIER',        '(?:\ *(?<= |^)\.(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\})(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\})??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\})??)');

// modifier .(title)[class]{style}<>
define('TEXY_MODIFIER_H',      '(?:\ *(?<= |^)\.(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))??)');
                               
// modifier .(title)[class]{style}<>^
define('TEXY_MODIFIER_HV',     '(?:\ *(?<= |^)\.(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??)');


// images   [* urls .(title)[class]{style} >]
define('TEXY_IMAGE',           '\[\*([^\n'.TEXY_HASH.']+)'.TEXY_MODIFIER.'? *(\*|>|<)\]');


// links
define('TEXY_LINK_REF',        '\[[^\[\]\*\n'.TEXY_HASH.']+\]');    // reference  [refName]
define('TEXY_LINK_IMAGE',      '\[\*[^\n'.TEXY_HASH.']+\*\]');      // [* ... *]
define('TEXY_LINK_URL',        '(?:\[[^\]\n]+\]|(?!\[)[^\s'.TEXY_HASH.']*?[^:);,.!?\s'.TEXY_HASH.'])'); // any url (nekonèí :).,!?
define('TEXY_LINK',            '(?::('.TEXY_LINK_URL.'))');    // any link
define('TEXY_LINK_N',          '(?::('.TEXY_LINK_URL.'|:))');  // any link (also unstated)
define('TEXY_EMAIL',           '[a-z0-9.+_-]+@[a-z0-9.+_-]{2,}\.[a-z]{2,}');    // name@exaple.com


/** modifiers patterns generator:

$TITLE =  '\([^\n\)]+\)';      // (...)
$CLASS =  '\[[^\n\]]+\]';      // [...]
$STYLE =  '\{[^\n\}]+\}';      // {...}
$HALIGN = '(?:<>|>|=|<)';      //  <  >  =  <>
$VALIGN = '(?:\^|\-|\_)';      //  ~ - _

// .(title)[class]{style}
define('TEXY_MODIFIER',
         "(?:\\ *(?<= |^)\\.".
         "($TITLE|$CLASS|$STYLE)".
         "($TITLE|$CLASS|$STYLE)??".
         "($TITLE|$CLASS|$STYLE)??)");


// .(title)[class]{style}<>
define('TEXY_MODIFIER_H',
         "(?:\\ *(?<= |^)\\.".
         "($TITLE|$CLASS|$STYLE|$HALIGN)".
         "($TITLE|$CLASS|$STYLE|$HALIGN)??".
         "($TITLE|$CLASS|$STYLE|$HALIGN)??".
         "($TITLE|$CLASS|$STYLE|$HALIGN)??)");


// .(title)[class]{style}<>^
define('TEXY_MODIFIER_HV',
         "(?:\\ *(?<= |^)\\.".
         "($TITLE|$CLASS|$STYLE|$HALIGN|$VALIGN)".
         "($TITLE|$CLASS|$STYLE|$HALIGN|$VALIGN)??".
         "($TITLE|$CLASS|$STYLE|$HALIGN|$VALIGN)??".
         "($TITLE|$CLASS|$STYLE|$HALIGN|$VALIGN)??".
         "($TITLE|$CLASS|$STYLE|$HALIGN|$VALIGN)??)");


foreach (get_defined_constants() as $name => $value) {
    $value = exportStr($value);
    if (substr($name, 0, 5) === 'TEXY_')
       echo "define('$name', '$value');\n";
}
*/

