<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
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

// images   [* urls .(title)[class]{style} >]
define('TEXY_PATTERN_IMAGE',    '\[\*([^\n'.TEXY_HASH.']+)'.TEXY_PATTERN_MODIFIER.'? *(\*|>|<)\]');


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


foreach (get_defined_constants() as $name => $value) {
    $value = exportStr($value);
    if (substr($name, 0, 5) === 'TEXY_')
       echo "define('$name', '$value');\n";
}
*/

?>