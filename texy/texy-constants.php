<?php

/**
 * ------------------------------
 *   TEXY!  REGULAR EXPRESSIONS
 * ------------------------------
 *
 * Version 0.9 beta
 *
 * Copyright (c) 2003-2005, David Grudl <dave@dgx.cz>
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



// ALIGN
define('TEXY_HALIGN_LEFT',    1);
define('TEXY_HALIGN_RIGHT',   2);
define('TEXY_HALIGN_CENTER',  3);
define('TEXY_HALIGN_JUSTIFY', 1 << 2);
define('TEXY_VALIGN_TOP',     2 << 2);
define('TEXY_VALIGN_MIDDLE',  3 << 2);
define('TEXY_VALIGN_BOTTOM',  4 << 2);


// LIST STYLE
define('TEXY_LIST_DEFINITION',       1);
define('TEXY_LIST_UNORDERED',        2);
define('TEXY_LIST_ORDERED',          3);
define('TEXY_LISTSTYLE_UPPER_ALPHA', 1 << 2);
define('TEXY_LISTSTYLE_LOWER_ALPHA', 2 << 2);
define('TEXY_LISTSTYLE_UPPER_ROMAN', 3 << 2);
// LIST ITEM TYPES
define('TEXY_LISTITEM',              0);
define('TEXY_LISTITEM_TERM',         1);
define('TEXY_LISTITEM_DEFINITION',   2);


// URL-TYPE
define('TEXY_LINK_ABSOLUTE',    1);
define('TEXY_LINK_RELATIVE',    2);
define('TEXY_LINK_EMAIL',       4);

define('TEXY_IMAGE',            1 << 3);
define('TEXY_IMAGE_OVER',       2 << 3);
define('TEXY_IMAGE_LINK',       4 << 3);




// REGULAR EXPRESSIONS

//     international characters 'A-Za-z\x86-\xff'
//     unicode                  'A-Za-z\x86-\x{ffff}'   
//     numbers                  0-9
//     spaces                   \n\r\t\x32
//     control                  \x00 - \x31  (without spaces)     
//     others                   !"#$%&'()*+,-./:;<=>?@[\]^_`{|}~

define ('TEXY_PATTERN_UTF',  TEXY_UTF8 ? 'u' : '');

// character classes
define('TEXY_CHAR',                    TEXY_UTF8 ? 'A-Za-z\x86-\x{ffff}' : 'A-Za-z\x86-\xff');   // INTERNATIONAL CHAR - USE INSTEAD OF \w (with TEXY_PATTERN_UTF)
define('TEXY_ALPHA',                   '0-9_'.TEXY_CHAR);  // INTERNATIONAL ALPHANUMERIC CHAR
define('TEXY_HASH',                    "\x15-\x1F");       // ANY HASH CHAR
define('TEXY_HASHSPACE',               "\x15-\x19");       // HASHED SPACE
define('TEXY_HASHEX',                  "([\x15-\x1F]*)");  // HASH SUBPATTERN


define('TEXY_NEWLINE',                 "\n");


// HTML tag & entity
//define('TEXY_PATTERN_XHTML_TAG',       '</?[a-z0-9]+[^>]*>');
define('TEXY_PATTERN_EMPTY_TAG',       '(br|img|input|hr)');
define('TEXY_PATTERN_INLINE_TAG',      '(a|abbr|acronym|b|br|cite|code|dfn|em|i|img|input|kbd|label|q|samp|select|button|big|small|span|strong|sub|sup|textarea|var)');
define('TEXY_PATTERN_BLOCK_TAG',       '(address|blockquote|div|dl|fieldset|form|h[1-6]|ol|p|pre|table|ul|dd|dt|li|td|th|tr)');
define('TEXY_PATTERN_EXTRA_TAG',       '(script|style)');  // use with modifier 's'

define('TEXY_PATTERN_ENTITY',          '&amp;(\\#?[a-z0-9]+;)');   // &amp;   |   &#039;   |   &123;



// hashes (for TexyFreezer)
define('TEXY_PATTERN_HASH',            "\x1A[\x1B-\x1F]+\x1A");


// modifiers
define('TEXY_PATTERN_TITLE',           '\([^\n\)]+\)');      // (...)
define('TEXY_PATTERN_CLASS',           '\[[^\n\]]+\]');      // [...]
define('TEXY_PATTERN_STYLE',           '\{[^\n\}]+\}');      // {...}
define('TEXY_PATTERN_HALIGN',          '(?:<>|>|=|<)');      //  <  >  =  <>
define('TEXY_PATTERN_VALIGN',          '(?:\^|\-|\_)');      //  ~ - _
define('TEXY_PATTERN_TABLESPAN',       '(?:[0-9]*/[0-9]*)'); //  3/1

define('TEXY_PATTERN_MODIFIER',         // .(title)[class]{style}
         '(?: ?\.('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.')'.
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
         
define('TEXY_PATTERN_MODIFIER_HVS',      // .(title)[class]{style}<>^1/2
         '(?: ?\.('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.'|'.TEXY_PATTERN_VALIGN.'|'.TEXY_PATTERN_TABLESPAN.')'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.'|'.TEXY_PATTERN_VALIGN.'|'.TEXY_PATTERN_TABLESPAN.')?'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.'|'.TEXY_PATTERN_VALIGN.'|'.TEXY_PATTERN_TABLESPAN.')?'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.'|'.TEXY_PATTERN_VALIGN.'|'.TEXY_PATTERN_TABLESPAN.')?'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.'|'.TEXY_PATTERN_VALIGN.'|'.TEXY_PATTERN_TABLESPAN.')?'.
         '('.TEXY_PATTERN_TITLE.'|'.TEXY_PATTERN_CLASS.'|'.TEXY_PATTERN_STYLE.'|'.TEXY_PATTERN_HALIGN.'|'.TEXY_PATTERN_VALIGN.'|'.TEXY_PATTERN_TABLESPAN.')?)');



// images   [* urls .(title)[class]{style} >]
define('TEXY_PATTERN_IMAGE',           '\[\*([^\n]+)'.TEXY_PATTERN_MODIFIER.'? *(\*|>|<)\]'); 


// links
define('TEXY_PATTERN_LINK_REF',        '\[[^\[\]\*\n]+\]');    // reference  [refName]
define('TEXY_PATTERN_LINK_IMAGE',      '\[\*[^\n]+\*\]');      // [* ... *]
define('TEXY_PATTERN_LINK_URL',        '(?:"[^"\n]+"|\'[^\'\n]+\'|(?-U)(?!\[)[^\s]*[^:);,.!?\s](?U))'); // any url (nekonèí :).,!?
define('TEXY_PATTERN_LINK_URL_GREEDY', '(?-U)(?!\[)\S+(?U)');  // any url
define('TEXY_PATTERN_LINK',            '(?-U)(?::(?U)('.TEXY_PATTERN_LINK_REF.'|'.TEXY_PATTERN_LINK_IMAGE.'|'.TEXY_PATTERN_LINK_URL.'))'); // any link
define('TEXY_PATTERN_EMAIL',           '[a-z0-9.+_-]+@[a-z0-9.+_-]{2,}\.[a-z]{2,}');    // name@exaple.com

?>