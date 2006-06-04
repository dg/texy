<?php

/**
 * ------------------------------
 *   TEXY!  REGULAR EXPRESSIONS
 * ------------------------------
 *
 * Version 1 Release Candidate
 *
 * Copyright (c) 2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * Regular Expression patterns for Texy!
 *
 * For the full copyright and license information, please view the COPYRIGHT
 * file that was distributed with this source code. If the COPYRIGHT file is
 * missing, please visit the Texy! homepage: http://www.texy.info
 *
 * @package Texy
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


// URL TYPES
define('TEXY_URL_ABSOLUTE',     1);
define('TEXY_URL_RELATIVE',     2);
define('TEXY_URL_EMAIL',        4);
define('TEXY_URL_IMAGE_INLINE', 1 << 3);
define('TEXY_URL_IMAGE_LINKED', 4 << 3);


// TEXY ELEMENT'S CONTENT TYPE
define('TEXY_CONTENT_NONE',    1);
define('TEXY_CONTENT_TEXTUAL', 2);
define('TEXY_CONTENT_BLOCK',   3);


// HTML ELEMENT CLASIFICATION
// notice 1: Constants may only evaluate to scalar values, so use serialize :-(
// notice 2: I use a little trick - isset($array[$item]) is much faster than in_array($item, $array)
define('TEXY_BLOCK_ELEMENTS',  serialize(array_flip(array('address', 'blockquote', 'caption', 'col', 'colgroup', 'dd', 'div', 'dl', 'dt', 'fieldset', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'iframe', 'legend', 'li', 'object', 'ol', 'p', 'param', 'pre', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul'))) );
define('TEXY_INLINE_ELEMENTS', serialize(array_flip(array('a', 'abbr', 'acronym', 'area', 'b', 'big', 'br', 'button', 'cite', 'code', 'del', 'dfn', 'em', 'i', 'img', 'input', 'ins', 'kbd', 'label', 'map', 'noscript', 'optgroup', 'option', 'q', 'samp', 'script', 'select', 'small', 'span', 'strong', 'sub', 'sup', 'textarea', 'tt', 'var'))) );
define('TEXY_EMPTY_ELEMENTS',  serialize(array_flip(array('img', 'hr', 'br', 'input', 'meta', 'area', 'base', 'col', 'link', 'param'))) );
define('TEXY_VALID_ELEMENTS',  serialize(array_merge(unserialize(TEXY_BLOCK_ELEMENTS), unserialize(TEXY_INLINE_ELEMENTS))) );
//define('TEXY_HEAD_ELEMENTS',   serialize(array_flip(array('html', 'head', 'body', 'base', 'meta', 'link', 'title'))) );

define('TEXY_EMPTY',    '/');
define('TEXY_CLOSING',  '*');


// CONFIGURATION DIRECTIVES
define('TEXY_ALL',   true);
define('TEXY_NONE',  false);



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
// HTML tag & entity
define('TEXY_PATTERN_ENTITY',   '&amp;([a-z]+|\\#x[0-9a-f]+|\\#[0-9]+);');   // &amp;   |   &#039;   |   &#x1A;


// modifiers
define('TEXY_PATTERN_TITLE',    '\([^\n\)]+\)');      // (...)
define('TEXY_PATTERN_CLASS',    '\[[^\n\]]+\]');      // [...]
define('TEXY_PATTERN_STYLE',    '\{[^\n\}]+\}');      // {...}
define('TEXY_PATTERN_HALIGN',   '(?:<>|>|=|<)');      //  <  >  =  <>
define('TEXY_PATTERN_VALIGN',   '(?:\^|\-|\_)');      //  ~ - _

define('TEXY_PATTERN_MODIFIER',         // .(title)[class]{style}
         '(?:\ *(?<= |^)\.('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.')'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.')??'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.')??)');

define('TEXY_PATTERN_MODIFIER_H',       // .(title)[class]{style}<>
         '(?:\ *(?<= |^)\.('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.')'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.')??'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.')??'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.')??)');

define('TEXY_PATTERN_MODIFIER_HV',      // .(title)[class]{style}<>^
         '(?:\ *(?<= |^)\.('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.'|'.TEXY_PATTERN_VALIGN.')'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.'|'.TEXY_PATTERN_VALIGN.')??'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.'|'.TEXY_PATTERN_VALIGN.')??'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.'|'.TEXY_PATTERN_VALIGN.')??'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.'|'.TEXY_PATTERN_VALIGN.')??)');





// links
define('TEXY_PATTERN_LINK_REF',        '\[[^\[\]\*\n'.TEXY_HASH.']+\]');    // reference  [refName]
define('TEXY_PATTERN_LINK_IMAGE',      '\[\*[^\n'.TEXY_HASH.']+\*\]');      // [* ... *]
define('TEXY_PATTERN_LINK_URL',        '(?:\[[^\]\n]+\]|(?!\[)[^\s'.TEXY_HASH.']*?[^:);,.!?\s'.TEXY_HASH.'])'); // any url (nekonèí :).,!?
define('TEXY_PATTERN_LINK',            '(?::('.TEXY_PATTERN_LINK_URL.'))');    // any link
define('TEXY_PATTERN_LINK_N',          '(?::('.TEXY_PATTERN_LINK_URL.'|:))');  // any link (also unstated)
define('TEXY_PATTERN_EMAIL',           '[a-z0-9.+_-]+@[a-z0-9.+_-]{2,}\.[a-z]{2,}');    // name@exaple.com

?>