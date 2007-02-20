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



// REGULAR EXPRESSION PATTERNS

//     international characters 'A-Za-z\x86-\xff'
//     unicode                  'A-Za-z\x86-\x{ffff}'
//     numbers                  0-9
//     spaces                   \n\r\t\x32
//     control                  \x00 - \x31  (without spaces)
//     others                   !"#$%&'()*+,-./:;<=>?@[\]^_`{|}~


// character classes
define('TEXY_CHAR',            'A-Za-z\x86-\x{ffff}');

// marking meta-charakters
define('TEXY_MARK',            "\x01-\x04\x14-\x1F");       // ANY MARK CHAR
define('TEXY_MARK_SPACES',     "\x01-\x04");       // MARKED SPACE
define('TEXY_MARK_N',          "\x14\x18-\x1F");    // marked CONTENT_NONE
define('TEXY_MARK_I',          "\x15\x18-\x1F");    // marked CONTENT_INLINE 
define('TEXY_MARK_T',          "\x16\x18-\x1F");    // marked CONTENT_TEXTUAL
define('TEXY_MARK_B',          "\x17\x18-\x1F");    // marked CONTENT_BLOCK


// modifier .(title)[class]{style}
define('TEXY_MODIFIER',        '(?:\ *(?<= |^)\.(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\})(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\})??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\})??)');

// modifier .(title)[class]{style}<>
define('TEXY_MODIFIER_H',      '(?:\ *(?<= |^)\.(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<))??)');
                               
// modifier .(title)[class]{style}<>^
define('TEXY_MODIFIER_HV',     '(?:\ *(?<= |^)\.(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\n\)]+\)|\[[^\n\]]+\]|\{[^\n\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??)');


// images   [* urls .(title)[class]{style} >]
define('TEXY_IMAGE',           '\[\*([^\n'.TEXY_MARK.']+)'.TEXY_MODIFIER.'? *(\*|>|<)\]');


// links
define('TEXY_LINK_REF',        '\[[^\[\]\*\n'.TEXY_MARK.']+\]');    // reference  [refName]
define('TEXY_LINK_IMAGE',      '\[\*[^\n'.TEXY_MARK.']+\*\]');      // [* ... *]
define('TEXY_LINK_URL',        '(?:\[[^\]\n]+\]|(?!\[)[^\s'.TEXY_MARK.']*?[^:);,.!?\s'.TEXY_MARK.'])'); // any url (nekonèí :).,!?
define('TEXY_LINK',            '(?::('.TEXY_LINK_URL.'))');    // any link
define('TEXY_LINK_N',          '(?::('.TEXY_LINK_URL.'|:))');  // any link (also unstated)
define('TEXY_EMAIL',           '[a-z0-9.+_-]+@[a-z0-9.+_-]{2,}\.[a-z]{2,}');    // name@exaple.com

