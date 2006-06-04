<?php

/**
 * ------------------------------
 *   TEXY!  REGULAR EXPRESSIONS
 * ------------------------------
 *
 * Version 1 Release Candidate
 *
 * Copyright (c) 2004-2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * Regular Expression patterns for Texy!
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 */


// security - include texy.php, not this file
if (!defined('TEXY')) die();


// XHTML
if (!defined('TEXY_XHTML'))
  define ('TEXY_XHTML', true);     // for empty elements, like <br /> vs. <br>



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


define('TEXY_CONTENT_NONE',    1);
define('TEXY_CONTENT_TEXTUAL', 2);
define('TEXY_CONTENT_BLOCK',   3);



define('TEXY_ELEMENT_VALID',   3);
define('TEXY_ELEMENT_BLOCK',   1 << 0);
define('TEXY_ELEMENT_INLINE',  1 << 1);
define('TEXY_ELEMENT_EMPTY',   1 << 2);



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
         '(?:\ ?\.('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.')'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.')?'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.')?)');

define('TEXY_PATTERN_MODIFIER_H',       // .(title)[class]{style}<>
         '(?: ?\.('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.')'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.')?'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.')?'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.')?)');

define('TEXY_PATTERN_MODIFIER_HV',      // .(title)[class]{style}<>^
         '(?: ?\.('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.'|'.TEXY_PATTERN_VALIGN.')'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.'|'.TEXY_PATTERN_VALIGN.')?'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.'|'.TEXY_PATTERN_VALIGN.')?'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.'|'.TEXY_PATTERN_VALIGN.')?'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.'|'.TEXY_PATTERN_VALIGN.')?)');





// links
define('TEXY_PATTERN_LINK_REF',        '\[[^\[\]\*\n'.TEXY_HASH.']+\]');    // reference  [refName]
define('TEXY_PATTERN_LINK_IMAGE',      '\[\*[^\n'.TEXY_HASH.']+\*\]');      // [* ... *]
define('TEXY_PATTERN_LINK_URL',        '(?:\[[^\]\n]+\]|(?-U)(?!\[)[^\s'.TEXY_HASH.']*[^:);,.!?\s'.TEXY_HASH.'](?U))'); // any url (nekonèí :).,!?
define('TEXY_PATTERN_LINK',            '(?-U)(?::(?U)('.TEXY_PATTERN_LINK_URL.'))');    // any link
define('TEXY_PATTERN_LINK_N',          '(?-U)(?::(?U)('.TEXY_PATTERN_LINK_URL.'|:))');  // any link (also unstated)
define('TEXY_PATTERN_EMAIL',           '[a-z0-9.+_-]+@[a-z0-9.+_-]{2,}\.[a-z]{2,}');    // name@exaple.com

?>