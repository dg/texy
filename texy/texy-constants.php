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


// UNICODE
if (!defined('TEXY_UTF8'))
  define ('TEXY_UTF8', false);     // UTF-8 input, slightly slower


// XHTML
if (!defined('TEXY_XHTML'))
  define ('TEXY_XHTML', true);     // for empty elements, like <br /> vs. <br>



// MODIFIERS - ALIGN
define('TEXY_HALIGN_LEFT',      1);
define('TEXY_HALIGN_RIGHT',     2);
define('TEXY_HALIGN_CENTER',    3);
define('TEXY_HALIGN_JUSTIFY',   1 << 2);
define('TEXY_VALIGN_TOP',       2 << 2);
define('TEXY_VALIGN_MIDDLE',    3 << 2);
define('TEXY_VALIGN_BOTTOM',    4 << 2);


// URL TYPES
define('TEXY_URL_ABSOLUTE',     1);
define('TEXY_URL_RELATIVE',     2);
define('TEXY_URL_EMAIL',        4);
define('TEXY_URL_IMAGE_INLINE', 1 << 3);
define('TEXY_URL_IMAGE_LINKED', 4 << 3);


// INLINE ELEMENTS PARTS
define('TEXY_WHOLE',           1);
define('TEXY_OPEN',            2);
define('TEXY_CLOSE',           3);

define('TEXY_CONTENT_NONE',    1);
define('TEXY_CONTENT_TEXTUAL', 2);
define('TEXY_CONTENT_HTML',    3);



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

define ('TEXY_PATTERN_UTF',     TEXY_UTF8 ? 'u' : '');

// character classes
define('TEXY_CHAR',             TEXY_UTF8 ? 'A-Za-z\x86-\x{ffff}' : 'A-Za-z\x86-\xff');   // INTERNATIONAL CHAR - USE INSTEAD OF \w (with TEXY_PATTERN_UTF)
define('TEXY_NEWLINE',          "\n");
// hashing meta-charakters
define('TEXY_SOFT',             0);
define('TEXY_HARD',             1);
define('TEXY_HASH',             "\x15-\x1F");       // ANY HASH CHAR
define('TEXY_HASH_SPACES',      "\x15-\x19");       // HASHED SPACE
define('TEXY_HASH_SOFT',        "\x1A\x1C-\x1F");   // HASHED TAG or ELEMENT (soft)
define('TEXY_HASH_HARD',        "\x1B-\x1F");       // HASHED TAG or ELEMENT (hard)
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



// images   [* urls .(title)[class]{style} >]
define('TEXY_PATTERN_IMAGE',    '\[\*([^\n'.TEXY_HASH.']+)'.TEXY_PATTERN_MODIFIER.'? *(\*|>|<)\]');


// links
define('TEXY_PATTERN_LINK_REF',        '\[[^\[\]\*\n'.TEXY_HASH.']+\]');    // reference  [refName]
define('TEXY_PATTERN_LINK_IMAGE',      '\[\*[^\n'.TEXY_HASH.']+\*\]');      // [* ... *]
define('TEXY_PATTERN_LINK_URL',        '(?:\[[^\]\n]+\]|(?-U)(?!\[)[^\s'.TEXY_HASH.']*[^:);,.!?\s'.TEXY_HASH.'](?U))'); // any url (nekonèí :).,!?
define('TEXY_PATTERN_LINK',            '(?-U)(?::(?U)('.TEXY_PATTERN_LINK_URL.'))');    // any link
define('TEXY_PATTERN_LINK_N',          '(?-U)(?::(?U)('.TEXY_PATTERN_LINK_URL.'|:))');  // any link (also unstated)
define('TEXY_PATTERN_EMAIL',           '[a-z0-9.+_-]+@[a-z0-9.+_-]{2,}\.[a-z]{2,}');    // name@exaple.com

?>