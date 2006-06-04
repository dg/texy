<?php

/**
 * --------------------------------------------
 *   TEXY!  * universal text->web converter *
 * --------------------------------------------
 *
 * Version 1 Release Candidate
 *
 * Copyright (c) 2004-2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * See documentation on website http://www.texy.info/
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
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */


define('TEXY', 'Version 1rc (c) David Grudl, http://www.texy.info');





// UNICODE
if (!defined('TEXY_UTF8'))
  define ('TEXY_UTF8', false);     // UTF-8 input, slightly slower


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

      // regular expressions & other constants







/**
 * MODIFIER PROCESSOR
 * ------------------
 *
 * Modifier is text like .(title)[class1 class2 #id]{color: red}>^
 *   .         starts with dot
 *   (...)     title or alt modifier
 *   [...]     classes or ID modifier
 *   {...}     inner style modifier
 *   < > <> =  horizontal align modifier
 *   ^ - _     vertical align modifier
 *
 */
class TexyModifier {
  var $texy; // parent Texy! object
  var $id;
  var $classes = array();
  var $unfilteredClasses = array();
  var $styles = array();
  var $unfilteredStyles = array();
  var $hAlign;
  var $vAlign;
  var $title;



  function TexyModifier(& $texy)
  {
    $this->texy = & $texy;
  }




  function setProperties()
  {
    $classes = '';
    $styles  = '';

    foreach (func_get_args() as $arg) {
      if ($arg == '') continue;
      $argX = trim(substr($arg, 1, -1));
      switch ($arg{0}) {
        case '{' :  $styles .= $argX . ';';  break;
        case '(' :  $this->title = $argX; break;
        case '[' :  $classes .= ' '.$argX; break;
        case '^' :  $this->vAlign = TEXY_VALIGN_TOP; break;
        case '-' :  $this->vAlign = TEXY_VALIGN_MIDDLE; break;
        case '_' :  $this->vAlign = TEXY_VALIGN_BOTTOM; break;
        case '=' :  $this->hAlign = TEXY_HALIGN_JUSTIFY; break;
        case '>' :  $this->hAlign = TEXY_HALIGN_RIGHT; break;
        case '<' :  $this->hAlign = $arg == '<>' ? TEXY_HALIGN_CENTER : TEXY_HALIGN_LEFT; break;
      }
    }

    $this->parseStyles($styles);
    $this->parseClasses($classes);

    if (isset($this->classes['id'])) {
      $this->id = $this->classes['id'];
      unset($this->classes['id']);
    }
  }




  function clear()
  {
    $this->id = null;
    $this->classes = array();
    $this->unfilteredClasses = array();
    $this->styles = array();
    $this->unfilteredStyles = array();
    $this->hAlign = null;
    $this->vAlign = null;
    $this->title = null;
  }


  function copyFrom(&$modifier)
  {
    $this->classes = $modifier->classes;
    $this->unfilteredClasses = $modifier->unfilteredClasses;
    $this->styles = $modifier->styles;
    $this->unfilteredStyles = $modifier->unfilteredStyles;
    $this->id = $modifier->id;
    $this->hAlign = $modifier->hAlign;
    $this->vAlign = $modifier->vAlign;
    $this->title = $modifier->title;
  }



  function implodeStyles($styles)
  {
    $styles = array_change_key_case($styles, CASE_LOWER);
    $pairs = array();
    foreach ($styles as $key => $value)
      if ($key && $value) $pairs[] = $key.':'.$value;
    return implode(';', $pairs);
  }



  function implodeClasses($classes)
  {
    return implode(' ', array_unique($classes) );
  }





  function parseClasses($str)
  {
    if (!$str) return;

    $tmp = is_array($this->texy->allowedClasses) ? array_flip($this->texy->allowedClasses) : array(); // little speed-up trick

    foreach (explode(' ', str_replace('#', ' #', $str)) as $value) {
      if ($value === '') continue;

      if ($value{0} == '#') {
        $this->unfilteredClasses['id'] = substr($value, 1);
        if ($this->texy->allowedClasses === true || isset($tmp[$value]))
          $this->classes['id'] = substr($value, 1);

      } else {
        $this->unfilteredClasses[] = $value;
        if ($this->texy->allowedClasses === true || isset($tmp[$value]))
          $this->classes[] = $value;
      }
    }
  }





  function parseStyles($str)
  {
    if (!$str) return;

    $tmp = is_array($this->texy->allowedStyles) ? array_flip($this->texy->allowedStyles) : array(); // little speed-up trick

    foreach (explode(';', $str) as $value) {
      $pair = explode(':', $value.':');
      $property = strtolower(trim($pair[0]));
      $value = trim($pair[1]);
      if (!$property || $value==='') continue;

      $this->unfilteredStyles[$property] = $value;
      if ($this->texy->allowedStyles === true || isset($tmp[$property]))
        $this->styles[$property] = $value;
    }
  }


} // TexyModifier






       // modifier processor







/**
 * TEXY! STRUCTURE FOR STORING URL
 * -------------------------------
 *
 * Analyse input text, detect type of url ($type)
 * and convert to final URL ($this->URL)
 *
 */
class TexyURL {
  var $texy;
  var $URL;
  var $type;
  var $text;
  var $root = '';    // root of relative link


  function TexyURL(&$texy)
  {
    $this->texy = & $texy;
  }


  function set($text, $type = null)
  {
    if ($type !== null)
      $this->type = $type;

    $this->text = trim($text);
    $this->analyse();
    $this->toURL();
  }


  function clear()
  {
    $this->text = '';
    $this->type = 0;
    $this->URL = '';
  }


  function copyFrom(&$obj)
  {
    $this->text = $obj->text;
    $this->type = $obj->type;
    $this->URL  = $obj->URL;
    $this->root = $obj->root;
  }


  function analyse()
  {
    if (preg_match('#^'.TEXY_PATTERN_EMAIL.'$#i', $this->text)) $this->type |= TEXY_URL_EMAIL;
    elseif (preg_match('#(https?://|ftp://|www\.|ftp\.|/)#Ai', $this->text)) $this->type |= TEXY_URL_ABSOLUTE;
    else $this->type |= TEXY_URL_RELATIVE;
  }



  function toURL()
  {
    if (!$this->text)
      return $this->URL = '';

    if ($this->type & TEXY_URL_EMAIL) {
      if ($this->texy->obfuscateEmail) {
        $this->URL = 'mai';
        $s = 'lto:'.$this->text;
        for ($i=0; $i<strlen($s); $i++)  $this->URL .= '&#' . ord($s{$i}) . ';';

      } else {
        $this->URL = 'mailto:'.$this->text;
      }
      return $this->URL;
    }

    if ($this->type & TEXY_URL_ABSOLUTE) {
      $textX = strtolower($this->text);

      if (substr($textX, 0, 4) == 'www.') {
        return $this->URL = 'http://'.$this->text;
      } elseif (substr($textX, 0, 4) == 'ftp.') {
        return $this->URL = 'ftp://'.$this->text;
      }
      return $this->URL = $this->text;
    }

    if ($this->type & TEXY_URL_RELATIVE) {
      return $this->URL = $this->root . $this->text;
    }
  }



  function toString()
  {
    if ($this->type & TEXY_URL_EMAIL) {
      return $this->texy->obfuscateEmail ? strtr($this->text, array('@' => '&nbsp;(at)&nbsp;')) : $this->text;
    }

    if ($this->type & TEXY_URL_ABSOLUTE) {
      $url = $this->text;
      $urlX = strtolower($url);
      if (substr($url, 0, 4) == 'www.') $url = '?://'.$url;
      if (substr($url, 0, 4) == 'ftp.') $url = '?://'.$url;

      $parts = parse_url($url);
      $res = '';
      if (isset($parts['scheme']) && $parts['scheme']{0} != '?')
        $res .= $parts['scheme'] . '://';

      if (isset($parts['host']))
        $res .= $parts['host'];

      if (isset($parts['path']))
        $res .=  (strlen($parts['path']) > 16 ? ('/...' . preg_replace('#^.*(.{0,12})$#U', '$1', $parts['path'])) : $parts['path']);

      if (isset($parts['query'])) {
        $res .= strlen($parts['query']) > 4 ? '?...' : ('?'.$parts['query']);
      } elseif (isset($parts['fragment'])) {
        $res .= strlen($parts['fragment']) > 4 ? '#...' : ('#'.$parts['fragment']);
      }
      return $res;
    }

    return $this->text;
  }


} // TexyURL






            // object encapsulate of URL







/**
 * Texy! ELEMENT BASE CLASS
 * ------------------------
 */
class TexyDOMElement {
  var $texy; // parent Texy! object
  var $hidden;


  // constructor
  function TexyDOMElement(&$texy)
  {
    $this->texy = & $texy;
  }


  // convert element to HTML string
  function toHTML()
  {
  }


  // for easy Texy! DOM manipulation
  function broadcast()
  {
    // build DOM->elements list
    $this->texy->DOM->elements[] = &$this;
  }


}  // TexyDOMElement








/**
 * HTML ELEMENT BASE CLASS
 * -----------------------
 *
 * This elements represents one HTML element
 *
 */
class TexyHTMLElement extends TexyDOMElement {
  var $tag;
  var $modifier;


  // constructor
  function TexyHTMLElement(&$texy) { // $parentModule = null, maybe in PHP5
    $this->texy = & $texy;
//    $this->parentModule = & $parentModule;
    $this->modifier = & $texy->createModifier();
  }



  function generateTag(&$tag, &$attr)
  {
    $tag  = $this->tag;

    $attr['id']    = $this->modifier->id;
    $attr['title'] = $this->modifier->title;
    $attr['class'] = TexyModifier::implodeClasses( $this->modifier->classes );

    $styles = $this->modifier->styles;
    $styles['text-align'] = $this->modifier->hAlign;
    $styles['vertical-align'] = $this->modifier->vAlign;
    $attr['style'] = TexyModifier::implodeStyles($styles);
  }



  // abstract
  function generateContent() { }


  // convert element to HTML string
  function toHTML()
  {
    $this->generateTag($tag, $attr);
    if ($this->hidden) return;

    return Texy::openingTag($tag, $attr)
           . $this->generateContent()
           . Texy::closingTag($tag);
  }



  function broadcast()
  {
    parent::broadcast();

    // build $texy->DOM->elementsById list
    if ($this->modifier->id)
      $this->texy->DOM->elementsById[$this->modifier->id] = &$this;

    // build $texy->DOM->elementsByClass list
    if ($this->modifier->classes)
      foreach ($this->modifier->classes as $class)
        $this->texy->DOM->elementsByClass[$class][] = &$this;
  }


}  // TexyHTMLElement











/**
 * BLOCK ELEMENT BASE CLASS
 * ------------------------
 *
 * This element represent array of other blocks (TexyHTMLElement)
 *
 */
class TexyBlockElement extends TexyHTMLElement {
  var $children = array(); // of TexyHTMLElement




  function generateContent()
  {
    $html = '';
    foreach (array_keys($this->children) as $key)
      $html .= $this->children[$key]->toHTML();

    return $html;
  }




  /***
   * Parse $text as BLOCK and create array children (array of Texy DOM elements)
   ***/
  function parse($text)
  {
    $block = &new TexyBlockParser($this);
    $block->parse($text);
  }



  function broadcast()
  {
    parent::broadcast();

    // apply to all children
    foreach (array_keys($this->children) as $key)
      $this->children[$key]->broadcast();
  }

}  // TexyBlockElement










/**
 * LINE OF TEXT
 * ------------
 *
 * This element represent one line of text.
 * Text represents $content and $children is array of TexyInlineTagElement
 *
 */
class TexyTextualElement extends TexyHTMLElement {
  var $children    = array();      // of TexyTextualElement

  var $content;                    // string
  var $contentType = TEXY_CONTENT_NONE;
  var $htmlSafe    = false;        // is content HTML-safe?

  var $strength    = TEXY_HARD;    // when used as child of TexyTextualElement
                                   // SOFT hasn't any visible content, HARD has (images, break-lines (!), ....)




  function setContent($text, $isHtmlSafe = false)
  {
    $this->content = $text;
    $this->htmlSafe = $isHtmlSafe;
  }



  function safeContent($onlyReturn = false)
  {
    $safeContent = $this->htmlSafe ? $this->content : htmlSpecialChars($this->content, ENT_QUOTES);

    if ($onlyReturn) return $safeContent;
    else {
      $this->content = $safeContent;
      $this->htmlSafe = true;
    }
  }




  function generateContent()
  {
    $content = $this->safeContent(true);

    if ($this->children) {
      $table = array();
      foreach (array_keys($this->children) as $key)
        $table[$key] = $this->children[$key][0]->toHTML( $this->children[$key][1] );

      return strtr($content, $table);
    }

    return $content;
  }



  /***
   * Parse $text as SINGLE LINE and create string $content and array of Texy DOM elements ($children)
   ***/
  function parse($text)
  {
    $line = &new TexyLineParser($this);
    $line->parse($text);
  }




  function toText()
  {
    return preg_replace('#['.TEXY_HASH.']+#', '', $this->content);
  }


  function broadcast()
  {
    parent::broadcast();

    // apply to all children
    foreach (array_keys($this->children) as $key)
      $this->children[$key][0]->broadcast();
  }




  function addTo(&$ownerElement)
  {
    $key = Texy::hashKey($this->strength);
    $ownerElement->children[$key]  = array(&$this, null);
    $ownerElement->contentType = max($ownerElement->contentType, $this->contentType);
    return $key;
  }

}  // TexyTextualElement







/**
 * INLINE TAG ELEMENT BASE CLASS
 * -----------------------------
 *
 * Represent HTML tags (elements without content)
 * Used as children of TexyTextualElement
 *
 */
class TexyInlineTagElement extends TexyHTMLElement {
  var $strength = TEXY_SOFT;
  var $_closingTag;



  // convert element to HTML string
  function toHTML($which)
  {
    switch ($which) {
      case TEXY_OPEN:
        $this->generateTag($tag, $attr);
        if ($this->hidden) return;
        $this->_closingTag = Texy::closingTag($tag);
        return Texy::openingTag($tag, $attr);

      case TEXY_CLOSE:
        return $this->_closingTag;
    }
  }



  function addTo(&$ownerElement, $elementContent = null)
  {
    $keyOpen = Texy::hashKey($this->strength);
    $keyClose = Texy::hashKey($this->strength);

    $ownerElement->children[$keyOpen]  = array(&$this, TEXY_OPEN);
    $ownerElement->children[$keyClose] = array(&$this, TEXY_CLOSE);
    return $keyOpen . $elementContent . $keyClose;
  }




} // TexyInlineTagElement

















/**
 * Texy! DOM
 * ---------
 */
class TexyDOM extends TexyBlockElement {
  var  $elements;
  var  $elementsById;
  var  $elementsByClass;


  /***
   * Convert Texy! document into DOM structure
   * Before converting it normalize text and call all pre-processing modules
   ***/
  function parse($text)
  {
      ///////////   REMOVE SPECIAL CHARS (used by Texy!)
    $text = strtr($text, "\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F", '           ');

      ///////////   STANDARDIZE LINE ENDINGS TO UNIX-LIKE  (DOS, MAC)
    $text = str_replace("\r\n", TEXY_NEWLINE, $text); // DOS
    $text = str_replace("\r", TEXY_NEWLINE, $text); // Mac
    $text = preg_replace("#[\t ]+(\n|$)#", TEXY_NEWLINE, $text); // right trim

      ///////////   REPLACE TABS WITH SPACES
    $tabWidth = $this->texy->tabWidth;
    while (strpos($text, "\t") !== false)
      $text = preg_replace_callback('#^(.*)\t#mU',
                 create_function('&$matches', "return \$matches[1] . str_repeat(' ', $tabWidth - strlen(\$matches[1]) % $tabWidth);"),
                 $text);


      ///////////   PRE-PROCESSING
    foreach (array_keys($this->texy->modules) as $name)
      $this->texy->modules[$name]->preProcess($text);

      ///////////   PROCESS
    parent::parse($text);
  }





  /***
   * Convert DOM structure to (X)HTML code
   * and call all post-processing modules
   * @return string
   ***/
  function toHTML()
  {
    $text = parent::toHTML();

      ///////////   POST-PROCESS
    foreach (array_keys($this->texy->modules) as $name)
      $this->texy->modules[$name]->postProcess($text);

      ///////////   UNFREEZE SPACES
    $text = Texy::unfreezeSpaces($text);

      // THIS (C) NOTICE SHOULD REMAIN!
    static $messageShowed = false;
    if (!$messageShowed) {
      $text .= "\n<!-- generated by Texy! -->";
      $messageShowed = true;
    }

    return $text;
  }




  /***
   * Build list for easy access to DOM structure
   ***/
  function buildLists()
  {
    $this->elements = array();
    $this->elementsById = array();
    $this->elementsByClass = array();
    $this->broadcast();
  }



}  // TexyDOM












/**
 * Texy! DOM for single line
 * -------------------------
 */
class TexyDOMLine extends TexyTextualElement {
  var  $elements;
  var  $elementsById;
  var  $elementsByClass;


  /***
   * Convert Texy! single line into DOM structure
   ***/
  function parse($text)
  {
      ///////////   REMOVE SPECIAL CHARS (used by Texy!)
    $text = strtr($text, "\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F", '           ');

      ///////////   REMOVE LINE ENDINGS
    $text = rtrim(strtr($text, array("\n" => ' ', "\r" => '')));

      ///////////   PROCESS
    parent::parse($text);
  }





  /***
   * Convert DOM structure to (X)HTML code
   * @return string
   ***/
  function toHTML()
  {
    $text = parent::toHTML();
    $text = Texy::unfreezeSpaces($text);
    return $text;
  }




  /***
   * Build list for easy access to DOM structure
   ***/
  function buildLists()
  {
    $this->elements = array();
    $this->elementsById = array();
    $this->elementsByClass = array();
    $this->broadcast();
  }



} // TexyDOMLine

            // Texy! DOM element's base class







/**
 * Texy! MODULES BASE CLASS
 * ------------------------
 */
class TexyModule {
  var $texy;             // parent Texy! object (reference to itself is: $texy->modules[__CLASSNAME__])
  var $allowed = true;   // general disable / enable



  function TexyModule(&$texy)
  {
    $this->texy = & $texy;
  }



  // register all line & block patterns a routines
  function init()
  {
  }


  // block's pre-process
  function preProcess(&$text)
  {
  }



  // block's post-process
  function postProcess(&$text)
  {
  }


/* not used yet
  // single line pre-process
  function linePreProcess(&$line)
  {
  }
*/

  // single line post-process
  function linePostProcess(&$line)
  {
  }



  /***
   * For easier regular expression writing
   * @return string
   * @static
   ***/
  function adjustPattern($pattern)
  {
    return strtr($pattern,
                     array('MODIFIER_HV' => TEXY_PATTERN_MODIFIER_HV,
                           'MODIFIER_H'  => TEXY_PATTERN_MODIFIER_H,
                           'MODIFIER'    => TEXY_PATTERN_MODIFIER,
                     ));
  }


  function registerLinePattern($func, $pattern, $user_args = null)
  {
    $this->texy->patternsLine[] = array(
             'replacement' => array(&$this, $func),
             'pattern'     => TexyModule::adjustPattern($pattern) ,
             'user'        => $user_args
    );
  }


  function registerBlockPattern($func, $pattern, $user_args = null)
  {
//    if (!preg_match('#(.)\^.*\$\\1[a-z]*#is', $pattern)) die('Texy: Not a block pattern. Class '.get_class($this).', pattern '.htmlSpecialChars($pattern));

    $this->texy->patternsBlock[] = array(
             'func'    => array(&$this, $func),
             'pattern' => TexyModule::adjustPattern($pattern)  . 'm',  // force multiline!
             'user'    => $user_args
    );
  }







  function isAllowed($what) {
    if ($this->allowed === true) return true;
    return (isset($this->allowed[$what])) ?
           ($this->allowed[$what] !== false) : false;
  }


  function allow($what = null, $value = true) {
    if ($what === null) $this->allowed = true;
    else $this->allowed[$what] = $value;
  }


  function disallow($what = null) {
    if ($what === null) $this->allowed = false;
    else $this->allowed[$what] = false;
  }


} // TexyModule





         // Texy! module base class









/**
 * BLOCK MODULE CLASS
 */
class TexyBlockModule extends TexyModule {
  var $allowed = array(
         'pre'  => true,
         'text' => true,
         'html' => true,         // if false, /--html blocks are parsed as /--text block
         'div'  => true,
         'form' => true,
      );
  var $userFunction;             // function &myUserFunc(&$element)


  // constructor
  function TexyBlockModule(&$texy)
  {
    parent::TexyModule($texy);
  }


  /***
   * Module initialization.
   */
  function init()
  {
    $this->registerBlockPattern('processBlock',   '#^/--+ *(?:(code|samp|text|html|div|form|notexy)( +\S*)?|) *MODIFIER_H?\n(.*\n)?\\\\--+()$#mUsi');
  }



  /***
   * Callback function (for blocks)
   * @return object
   *
   *            /-----code html .(title)[class]{style}
   *              ....
   *              ....
   *            \----
   *
   */
  function processBlock(&$blockParser, &$matches)
  {
    list($match, $mType, $mSecond, $mMod1, $mMod2, $mMod3, $mMod4, $mContent) = $matches;
    //    [1] => code
    //    [2] => lang ?
    //    [3] => (title)
    //    [4] => [class]
    //    [5] => {style}
    //    [6] => >
    //    [7] => .... content

    $mType = trim(strtolower($mType));
    $mSecond = trim(strtolower($mSecond));
    $mContent = trim($mContent, "\n");

    if (!$mType) $mType = 'pre';                // default type
    if ($mType == 'notexy') $mType = 'html'; // backward compatibility
    if ($mType == 'html' && !$this->isAllowed('html')) $mType = 'text';
    if ($mType == 'code' || $mType == 'samp')
      $mType = $this->isAllowed('pre') ? $mType : 'none';
    elseif (!$this->isAllowed($mType)) $mType = 'none'; // transparent block

    switch ($mType) {
     case 'none':
     case 'div':
         $el = &new TexyBlockElement($this->texy);
         $el->tag = $mType;
         $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
         // outdent
         if ($spaces = strspn($mContent, ' '))
           $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

         $el->parse($mContent);
         $blockParser->addChildren($el);
         break;


     case 'form':
         $el = &new TexyFormElement($this->texy);
         $el->action->set($mSecond);
         $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
         // outdent
         if ($spaces = strspn($mContent, ' '))
           $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

         $el->parse($mContent);
         $blockParser->addChildren($el);
         break;


     case 'html':
         $el = &new TexyTextualElement($this->texy);
         $el->setContent($mContent, true);
         $blockParser->addChildren($el);
         break;


     case 'text':
         $el = &new TexyTextualElement($this->texy);
         $el->setContent(
                (
                   nl2br(
                     Texy::htmlChars($mContent)
                   )
                ),
                true);
         $blockParser->addChildren($el);
         break;


     default: // pre | code | samp
         $el = &new TexyCodeBlockElement($this->texy);
         $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
         $el->type = $mType;
         $el->lang = $mSecond;

         // outdent
         if ($spaces = strspn($mContent, ' '))
           $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

         $el->setContent($mContent, false); // not html-safe content

         if ($this->userFunction)
           call_user_func_array($this->userFunction, array(&$el));

         $blockParser->addChildren($el);

    } // switch
  }



  function trustMode()
  {
    $this->allow('html');
    $this->allow('form');
  }



  function safeMode()
  {
    $this->disallow('html');
    $this->disallow('form');
  }


} // TexyBlockModule







/****************************************************************************
                               TEXY! DOM ELEMENTS                          */





/**
 * HTML ELEMENT PRE + CODE
 */
class TexyCodeBlockElement extends TexyTextualElement {
  var $tag = 'pre';
  var $lang;
  var $type;


  function generateTag(&$tag, &$attr)
  {
    parent::generateTag($tag, $attr);

    $classes = $this->modifier->classes;
    $classes[] = $this->lang;
    $attr['class'] = TexyModifier::implodeClasses($classes);
  }



  function toHTML()
  {
    $this->generateTag($tag, $attr);
    if ($this->hidden) return;

    return   Texy::openingTag($tag, $attr)
           . Texy::openingTag($this->type)

           . $this->generateContent()

           . Texy::closingTag($this->type)
           . Texy::closingTag($tag);
  }

} // TexyCodeBlockElement






/**
 * HTML ELEMENT FORM
 */
class TexyFormElement extends TexyBlockElement {
  var $tag = 'form';
  var $action;
  var $post = true;


  function TexyFormElement(&$texy)
  {
    parent::TexyBlockElement($texy);
    $this->action = & $texy->createURL();
  }


  function generateTag(&$tag, &$attr)
  {
    parent::generateTag($tag, $attr);

    if ($this->action->URL) $attr['action'] = $this->action->URL;
    $attr['method'] = $this->post ? 'post' : 'get';
    $attr['enctype'] = $this->post ? 'multipart/form-data' : '';
  }




} // TexyFormElement













/**
 * CONTROL MODULE CLASS - EXPERIMENTAL !!!
 */
class TexyControlModule extends TexyModule {
  var $allowed = array(
         'text'      => true,
         'select'    => true,
         'radio'     => true,
         'checkbox'  => true,
         'button'    => true,
      );


  // constructor
  function TexyControlModule(&$texy)
  {
    parent::TexyModule($texy);
  }


  /***
   * Module initialization.
   */
  function init()
  {
  }


  function trustMode()
  {
  }



  function safeMode()
  {
  }


} // TexyControlModule







/****************************************************************************
                               TEXY! DOM ELEMENTS                          */











// LIST STYLE
define('TEXY_LIST_DEFINITION',       'dl');
define('TEXY_LIST_UNORDERED',        'ul');
define('TEXY_LIST_ORDERED',          'ol');
define('TEXY_LISTSTYLE_UPPER_ALPHA', 'upper-alpha');
define('TEXY_LISTSTYLE_LOWER_ALPHA', 'lower-alpha');
define('TEXY_LISTSTYLE_UPPER_ROMAN', 'upper-roman');

// LIST ITEM TYPES
define('TEXY_LISTITEM',              'li');
define('TEXY_LISTITEM_TERM',         'dt');
define('TEXY_LISTITEM_DEFINITION',   'dd');


/**
 * ORDERED / UNORDERED NESTED LIST MODULE CLASS
 */
class TexyListModule extends TexyModule {


  /***
   * Module initialization.
   */
  function init()
  {
    if ($this->allowed)
      $this->registerBlockPattern('processBlock', '#^(?:MODIFIER_H\n)?'                                                     // .{color: red}
                                                . '(\*|\-|\+|\d+\.|\d+\)|[a-zA-Z]+\)|[IVX]+\.)\ +(.*)MODIFIER_H?()$#mU');   // - item
  }



  /***
   * Callback function (for blocks)
   *
   *            1) .... .(title)[class]{style}>
   *            2) ....
   *                + ...
   *                + ...
   *            3) ....
   *
   */
  function processBlock(&$blockParser, &$matches)
  {
    list($match, $mModList1, $mModList2, $mModList3, $mModList4, $mType, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
    //    [1] => (title)
    //    [2] => [class]
    //    [3] => {style}
    //    [4] => >

    //    [5] => * + - 1. 1) a) A) IV.
    //    [6] => ...
    //    [7] => (title)
    //    [8] => [class]
    //    [9] => {style}
    //   [10] => >

    $texy = & $this->texy;
    $el = &new TexyListElement($texy);
    $el->modifier->setProperties($mModList1, $mModList2, $mModList3, $mModList4);
    do {
      $type = '\*';       if (preg_match("#$type#A", $mType)) { $el->type = TEXY_LIST_UNORDERED; break; }
      $type = '\-';       if (preg_match("#$type#A", $mType)) { $el->type = TEXY_LIST_UNORDERED; break; }
      $type = '\+';       if (preg_match("#$type#A", $mType)) { $el->type = TEXY_LIST_UNORDERED; break; }
      $type = '\d+\.';    if (preg_match("#$type#A", $mType)) { $el->type = TEXY_LIST_ORDERED; break; }
      $type = '\d+\)';    if (preg_match("#$type#A", $mType)) { $el->type = TEXY_LIST_ORDERED; break; }
      $type = '[A-Z]+\)'; if (preg_match("#$type#A", $mType)) { $el->type = TEXY_LIST_ORDERED; $el->style = TEXY_LISTSTYLE_UPPER_ALPHA; break; }
      $type = '[a-z]+\)'; if (preg_match("#$type#A", $mType)) { $el->type = TEXY_LIST_ORDERED; $el->style = TEXY_LISTSTYLE_LOWER_ALPHA; break; }
      $type = '[IVX]+\.'; if (preg_match("#$type#A", $mType)) { $el->type = TEXY_LIST_ORDERED; $el->style = TEXY_LISTSTYLE_UPPER_ROMAN; break; }
    } while (false);


    $reItem = "#^$type\ +(.*)".TEXY_PATTERN_MODIFIER_H.'?()$#AUm';


    do {
      $elItem = &new TexyListItemElement($texy);
      $el->children[] = & $elItem;
      $elItem->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
      $content = ' ';             // trick: don't recognize `- 12. 3.` as three nested lists
      $spaces = '';

      do {
        $content .= $mContent . TEXY_NEWLINE;

        if ($blockParser->match("#^(?:|(\ {1,$spaces})(.*))()$#Am", $matches)) {
          list($match, $mSpaces, $mContent) = $matches;
          //    [1] => SPACES
          //    [2] => ...
          if ($match != '' && $spaces === '') $spaces = strlen($mSpaces);
          continue;
        }

        break;
      } while (true);

      $elItem->parse($content);
      if (is_a($elItem->children[0], 'TexyGenericBlockElement')) $elItem->children[0]->tag = '';

      if (!$blockParser->match($reItem, $matches)) break;
      list($match, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
        //    [1] => ...
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => >

    } while (true);


    $blockParser->addChildren($el);
  }




} // TexyListModule






/****************************************************************************
                               TEXY! DOM ELEMENTS                          */




/**
 * HTML ELEMENT OL / UL / DL
 */
class TexyListElement extends TexyBlockElement {
  var $type;
  var $style;


  function generateTag(&$tag, &$attr)
  {
    $tag = $this->type;

    $attr['id']    = $this->modifier->id;
    $attr['title'] = $this->modifier->title;
    $attr['class'] = TexyModifier::implodeClasses( $this->modifier->classes );

    $styles = $this->modifier->styles;
    $styles['text-align'] = $this->modifier->hAlign;
    $styles['list-style-type'] = $this->style;
    $attr['style'] = TexyModifier::implodeStyles($styles);
  }


} // TexyListElement





/**
 * HTML ELEMENT LI / DL / DT
 */
class TexyListItemElement extends TexyBlockElement {
  var $type = TEXY_LISTITEM;


  function generateTag(&$tag, &$attr)
  {
    parent::generateTag($tag, $attr);
    $tag = $this->type;
  }

} // TexyListItemElement










/**
 * DEFINITION LIST MODULE CLASS
 */
class TexyDefinitionListModule extends TexyListModule {


  /***
   * Module initialization.
   */
  function init()
  {
    if ($this->allowed)
      $this->registerBlockPattern('processBlock', '#^(?:MODIFIER_H\n)?'                         // .{color:red}
                                                . '(\S.*)\:\ *MODIFIER_H?\n'                    // Term:
                                                . '(\ +)(\*|\-|\+)\ +(.*)MODIFIER_H?()$#mU');   //    - description
  }



  /***
   * Callback function (for blocks)
   *
   *            Term: .(title)[class]{style}>
   *              - description 1
   *              - description 2
   *              - description 3
   *
   */
  function processBlock(&$blockParser, &$matches)
  {
    list($match, $mModList1, $mModList2, $mModList3, $mModList4,
                 $mContentTerm, $mModTerm1, $mModTerm2, $mModTerm3, $mModTerm4,
                 $mSpaces, $mType, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
    //    [1] => (title)
    //    [2] => [class]
    //    [3] => {style}
    //    [4] => >

    //    [5] => ...
    //    [6] => (title)
    //    [7] => [class]
    //    [8] => {style}
    //    [9] => >

    //   [10] => space
    //   [11] => - * +
    //   [12] => ...
    //   [13] => (title)
    //   [14] => [class]
    //   [15] => {style}
    //   [16] => >

    $texy = & $this->texy;
    $el = &new TexyListElement($texy);
    $el->type = TEXY_LIST_DEFINITION;
    $el->modifier->setProperties($mModList1, $mModList2, $mModList3, $mModList4);

    $reTerm = '#^(\S.*)\:\ *MODIFIER_H?' . TEXY_NEWLINE .'(\ +)(\*|\-|\+)\ +(.*)MODIFIER_H?()$#mUA';

    do {
      $mType = preg_quote($mType);
      $spacesBase = strlen($mSpaces);
      $reItem = "#^(\ {1,$spacesBase})$mType\ +(.*)".TEXY_PATTERN_MODIFIER_H."?()$#mA";

      $elItem = &new TexyListItemElement($texy);
      $elItem->type = TEXY_LISTITEM_TERM;
      $el->children[] = & $elItem;
      $elItem->modifier->setProperties($mModTerm1, $mModTerm2, $mModTerm3, $mModTerm4);
      $elItem->parse($mContentTerm);
      if (is_a($elItem->children[0], 'TexyGenericBlockElement')) $elItem->children[0]->tag = '';

      do {
        $elItem = &new TexyListItemElement($texy);
        $elItem->type = TEXY_LISTITEM_DEFINITION;
        $el->children[] = & $elItem;
        $elItem->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $content = '';
        $spaces = '';

        do {
          $content .= $mContent . TEXY_NEWLINE;

          if ($blockParser->match("#^(?:|\ \{$spacesBase}(\ {1,$spaces})(.*))()$#Am", $matches)) {
            list($match, $mSpaces, $mContent) = $matches;
            //    [1] => SPACE2
            //    [2] => ...
            if ($match != '' && $spaces === '') $spaces = strlen($mSpaces);
            continue;
          }

          break;
        } while (true);

        $elItem->parse($content);
        if (is_a($elItem->children[0], 'TexyGenericBlockElement')) $elItem->children[0]->tag = '';

        if ($blockParser->match($reItem, $matches)) {
          list($match, $mSpaces, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
          //    [1] => SPACE
          //    [2] => ...
          //    [3] => (title)
          //    [4] => [class]
          //    [5] => {style}
          //    [6] => >
          continue;
        }

        break;
      } while (true);

      if ($blockParser->match($reTerm, $matches)) {
        list($match, $mContentTerm, $mModTerm1, $mModTerm2, $mModTerm3, $mModTerm4, $mSpaces, $mType, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
        continue;
      }

      break;
    } while (true);


    $blockParser->addChildren($el);
  }

} // TexyDefinitionListModule














/**
 * MODULE BASE CLASS
 */
class TexyFormatterModule extends TexyModule {
  var $baseIndent  = 0;               // indent for top elements
  var $lineWrap    = 80;              // line width, doesn't include indent space
  var $indent      = true;

  // internal
  var $tagStack;
  var $tagStackAssoc;
  var $nestedElements = array('div', 'dl', 'ol', 'ul', 'blockquote', 'li', 'dd');
  var $hashTable = array();



  // constructor
  function TexyFormatterModule(&$texy)
  {
    parent::TexyModule($texy);

    // little trick - isset($array[$item]) is much faster than in_array($item, $array)
    $this->nestedElements = array_flip($this->nestedElements);
  }





  function postProcess(&$text)
  {
    $this->wellForm($text);

    if ($this->indent)
      $this->indent($text);
  }



  /***
   * Convert <strong><em> ... </strong> ... </em>
   *    into <strong><em> ... </em></strong><em> ... </em>
   */
  function wellForm(&$text)
  {
    $this->tagStack = array();
    $this->tagStackAssoc = array();
    $text = preg_replace_callback('#<(/?)([a-z][a-z0-9]*)(|\s.*|:.*)(/?)>()#Uis', array(&$this, '_replaceWellForm'), $text);
    if ($this->tagStack) {
      $pair = end($this->tagStack);
      while ($pair !== false) {
        $text .= '</'.$pair[0].'>';
        $pair = prev($this->tagStack);
      }
    }
  }



  /***
   * Callback function: <tag> | </tag>
   * @return string
   */
  function _replaceWellForm(&$matches)
  {
    list($match, $mClosing, $mTag, $mAttr, $mEmpty) = $matches;
    //    [1] => /
    //    [2] => TAG
    //    [3] => ... (attributes)
    //    [4] => /   (empty)


    if (isset($this->texy->emptyElements[$mTag]) || $mEmpty) return $mClosing ? '' : $match;

    if ($mClosing) {  // closing
      $pair = end($this->tagStack);
      $s = '';
      $i = 1;
      while ($pair !== false) {
        if (!$pair[2]) $s .= '</'.$pair[0].'>';
        if ($pair[0] == $mTag) break;
        $pair = prev($this->tagStack);
        $i++;
      }
      if ($pair[0] <> $mTag) return '';
      if (!$pair[2]) unset($this->tagStackAssoc[$mTag]);

      if (isset($this->texy->blockElements[$mTag])) {
        array_splice($this->tagStack, -$i);
        return $s;
      }

      unset($this->tagStack[key($this->tagStack)]);
      $pair = current($this->tagStack);
      while ($pair !== false) {
        if (!$pair[2]) $s .= '<'.$pair[0].$pair[1].'>';
        $pair = next($this->tagStack);
      }
      return $s;

    } else {
                     // opening
      $hide = isset($this->tagStackAssoc[$mTag]);
      if (!isset($this->nestedElements[$mTag])) $this->tagStackAssoc[$mTag] = true;
      $this->tagStack[] = array($mTag, $mAttr, $hide);
      return $hide ? '' : $match;
    }
  }




  /***
   * Output HTML formating
   */
  function indent(&$text)
  {
    $text = preg_replace_callback('#<(pre|textarea|script|style)(.*)</\\1>#Uis', array(&$this, '_freeze'), $text);
    $this->_indent = $this->baseIndent;
    $text = str_replace(TEXY_NEWLINE, '', $text);
    $text = preg_replace('# +#', ' ', $text);
    $text = preg_replace_callback('# *<(/?)(' . implode(array_keys($this->texy->blockElements), '|') . '|br)(>| [^>]*>) *#', array(&$this, '_replaceReformat'), $text);
    $text = preg_replace('#(?<=\S)\t+#m', '', $text);
    $text = preg_replace_callback('#^(\t*)(.*)$#m', array(&$this, '_replaceWrapLines'), $text);

    $text = strtr($text, $this->hashTable); // unfreeze
  }



  // create new unique key for string $matches[0]
  // and saves pair (key => str) into table $this->hashTable
  function _freeze(&$matches)
  {
    $key = '<'.$matches[1].'>' . Texy::hashKey() . '</'.$matches[1].'>';
    $this->hashTable[$key] = $matches[0];
    return $key;
  }




  /***
   * Callback function: Insert \n + spaces into HTML code
   * @return string
   */
  function _replaceReformat(&$matches)
  {
    list($match, $mClosing, $mTag) = $matches;
    //    [1] => /  (opening or closing element)
    //    [2] => element
    //    [3] => attributes>
    $match = trim($match);
    if ($mClosing == '/') {
      return str_repeat("\t", --$this->_indent) . $match . TEXY_NEWLINE;
    } else {
      if ($mTag == 'hr') return TEXY_NEWLINE . str_repeat("\t", $this->_indent) . $match . TEXY_NEWLINE;
      if (isset($this->texy->emptyElements[$mTag])) $this->_indent--;
      return TEXY_NEWLINE . str_repeat("\t", max(0, $this->_indent++)) . $match;
    }
  }




  /***
   * Callback function: wrap lines
   * @return string
   */
  function _replaceWrapLines(&$matches)
  {
    list($match, $mSpace, $mContent) = $matches;
    return $mSpace . str_replace(TEXY_NEWLINE, TEXY_NEWLINE.$mSpace, wordwrap($mContent, $this->lineWrap));
  }



} // TexyFormatterModule












/**
 * PARAGRAPH / GENERIC MODULE CLASS
 */
class TexyGenericBlockModule extends TexyModule {




  /***
   * Module initialization
   */
  function init()
  {
    $this->texy->genericBlock = array(&$this, 'processBlock');
  }



  function processBlock(&$blockParser, $content)
  {
    $str_blocks = preg_split('#(\n{2,})#', $content);

    foreach ($str_blocks as $str) {
      $str = trim($str);
      if (!$str) continue;
      $this->processSingleBlock($blockParser, $str);
    }
  }



  /***
   * Callback function (for blocks)
   *
   *            ....  .(title)[class]{style}>
   *             ...
   *             ...
   *
   */
  function &processSingleBlock(&$blockParser, $content)
  {
    preg_match('#^(.+)'.TEXY_PATTERN_MODIFIER_H.'?(\n.*)?()$#sU', $content, $matches);
    list($match, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mContent2) = $matches;
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => >


    // PARAGRAPH or DIV

    $el = &new TexyGenericBlockElement($this->texy);
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

    // ....
    //  ...  => \n
    $mContent = preg_replace('#\n (\S)#', " \r\\1", trim($mContent . $mContent2));
    $mContent = strtr($mContent, "\n\r", " \n");

    $el->parse($mContent);

    // specify tag
    if ($el->contentType == TEXY_CONTENT_TEXTUAL) $el->tag = 'p';
    elseif ($mMod1 || $mMod2 || $mMod3 || $mMod4) $el->tag = 'div';
    elseif ($el->contentType == TEXY_CONTENT_HTML) $el->tag = '';
    else $el->tag = 'div';

    // add <br />
    if ($el->tag && (strpos($el->content, "\n") !== false)) {
      $elBr = &new TexyLineBreakElement($this->texy);
      $el->content = strtr($el->content,
                        array("\n" => $elBr->addTo($el))
                     );
    }

    $blockParser->addChildren($el);
  }





} // TexyGenericBlockModule








/****************************************************************************
                               TEXY! DOM ELEMENTS                          */



/**
 * HTML ELEMENT LINE BREAK
 */
class TexyLineBreakElement extends TexyTextualElement {
  var $tag = 'br';

} // TexyLineBreakElement




/**
 * HTML ELEMENT PARAGRAPH / DIV / TRANSPARENT
 */
class TexyGenericBlockElement extends TexyTextualElement {
  var $tag = 'p';

} // TexyGenericBlockElement











define('TEXY_HEADING_DYNAMIC',         1);  // auto-leveling
define('TEXY_HEADING_FIXED',           2);  // fixed-leveling




/**
 * HEADING MODULE CLASS
 */
class TexyHeadingModule extends TexyModule {
  var $allowed = array(
         'surround'  => true,
         'underline' => true,
      );

  // options
  var $top    = 1;                      // number of top heading, 1 - 6
  var $title;                           // textual content of first heading
  var $balancing = TEXY_HEADING_DYNAMIC;
  var $levels = array(            // when $balancing = TEXY_HEADING_FIXED
                         '#' => 0,      //   #  -->  $levels['#'] + $top = 0 + 1 = 1  --> <h1> ... </h1>
                         '*' => 1,
                         '=' => 2,
                         '-' => 3,
                         7   => 0,
                         6   => 1,
                         5   => 2,
                         4   => 3,
                         3   => 4,
                         2   => 5,
                       );

  // private
  var $_rangeUnderline;
  var $_deltaUnderline;
  var $_rangeSurround;
  var $_deltaSurround;



  /***
   * Module initialization.
   */
  function init()
  {
    if ($this->isAllowed('underline'))
      $this->registerBlockPattern('processBlockUnderline', '#^(\S.*)MODIFIER_H?' . TEXY_NEWLINE
                                                        .'(\#|\*|\=|\-){3,}$#mU');
    if ($this->isAllowed('surround'))
      $this->registerBlockPattern('processBlockSurround',  '#^((\#|\=){2,7})(?!\\2)(.*)\\2*MODIFIER_H?()$#mU');
  }


  /***
   * Preprocessing
   */
  function preProcess(&$text)
  {
    $this->_rangeUnderline = array(10, 0);
    $this->_rangeSurround    = array(10, 0);
    unset($this->_deltaUnderline);
    unset($this->_deltaSurround);
  }


  /***
   * Callback function (for blocks)
   *
   *            Heading .(title)[class]{style}>
   *            -------------------------------
   *
   */
  function processBlockUnderline(&$blockParser, &$matches)
  {
    list($match, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mLine) = $matches;
    //  $matches:
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => >
    //
    //    [6] => ...

    $el = &new TexyHeadingElement($this->texy);
    $el->level = $this->levels[$mLine];
    if ($this->balancing == TEXY_HEADING_DYNAMIC)
      $el->deltaLevel = & $this->_deltaUnderline;

    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
    $el->parse(trim($mContent));
    if (!$this->title) $this->title = $el->toText();

    // dynamic headings balancing
    $this->_rangeUnderline[0] = min($this->_rangeUnderline[0], $el->level);
    $this->_rangeUnderline[1] = max($this->_rangeUnderline[1], $el->level);
    $this->_deltaUnderline    = -$this->_rangeUnderline[0];
    $this->_deltaSurround     = -$this->_rangeSurround[0] + ($this->_rangeUnderline[1] ? ($this->_rangeUnderline[1] - $this->_rangeUnderline[0] + 1) : 0);

    $blockParser->addChildren($el);
  }



  /***
   * Callback function (for blocks)
   *
   *            ### Heading .(title)[class]{style}>
   *
   */
  function processBlockSurround(&$blockParser, &$matches)
  {
    list($match, $mLine, $mChar, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
    //    [1] => ###
    //    [2] => ...
    //    [3] => (title)
    //    [4] => [class]
    //    [5] => {style}
    //    [6] => >

    $el = &new TexyHeadingElement($this->texy);
    $el->level = $this->levels[strlen($mLine)];
    if ($this->balancing == TEXY_HEADING_DYNAMIC)
      $el->deltaLevel = & $this->_deltaSurround;

    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
    $el->parse(trim($mContent));
    if (!$this->title) $this->title = $el->toText();

    // dynamic headings balancing
    $this->_rangeSurround[0] = min($this->_rangeSurround[0], $el->level);
    $this->_rangeSurround[1] = max($this->_rangeSurround[1], $el->level);
    $this->_deltaSurround    = -$this->_rangeSurround[0] + ($this->_rangeUnderline[1] ? ($this->_rangeUnderline[1] - $this->_rangeUnderline[0] + 1) : 0);

    $blockParser->addChildren($el);
  }




} // TexyHeadingModule







/****************************************************************************
                               TEXY! DOM ELEMENTS                          */






/**
 * HTML ELEMENT H1-6
 */
class TexyHeadingElement extends TexyTextualElement {
  var $parentModule;
  var $level = 0;        // 0 .. ?
  var $deltaLevel = 0;


  // constructor
  function TexyHeadingElement(&$texy)
  {
    parent::TexyTextualElement($texy);
    $this->parentModule = & $texy->modules['TexyHeadingModule'];
  }


  function generateTag(&$tag, &$attr)
  {
    parent::generateTag($tag, $attr);
    $tag = 'h' . min(6, max(1, $this->level + $this->deltaLevel + $this->parentModule->top));
  }

} // TexyHeadingElement

















/**
 * HORIZONTAL LINE MODULE CLASS
 */
class TexyHorizlineModule extends TexyModule {


  /***
   * Module initialization.
   */
  function init()
  {
    if ($this->allowed)
      $this->registerBlockPattern('processBlock', '#^(\- |\-|\* |\*){3,}\ *MODIFIER_H?()$#mU');
  }



  /***
   * Callback function (for blocks)
   *
   *            ---------------------------
   *
   *            - - - - - - - - - - - - - -
   *
   *            ***************************
   *
   *            * * * * * * * * * * * * * *
   *
   */
  function processBlock(&$blockParser, &$matches)
  {
    list($match, $mLine, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
    //    [1] => ---
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => >

    $el = &new TexyHorizLineElement($this->texy);
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
    $blockParser->addChildren($el);
  }




} // TexyHorizlineModule






/****************************************************************************
                               TEXY! DOM ELEMENTS                          */




/**
 * HTML ELEMENT HORIZONTAL LINE
 */
class TexyHorizLineElement extends TexyBlockElement {
  var $tag = 'hr';

} // TexyHorizLineElement












/**
 * HTML TAGS MODULE CLASS
 */
class TexyHTMLTagModule extends TexyModule {
  var $allowed;          // allowed tags (true -> all, or array, or false -> none)
//  var $userFunction;   // function &myUserFunc(&$texy, &$tag, &$attr)  $attr = false --> closing tag

                         // arrays of safe tags and attributes
  var $safeTags = array(
                     'a'         => array('href', 'rel', 'title'),
                     'abbr'      => array('title'),
                     'acronym'   => array('title'),
                     'b'         => array(),
                     'br'        => array(),
                     'cite'      => array(),
                     'code'      => array(),
                     'dfn'       => array(),
                     'em'        => array(),
                     'i'         => array(),
                     'kbd'       => array(),
                     'q'         => array('cite'),
                     'samp'      => array(),
                     'small'     => array(),
                     'span'      => array('title'),
                     'strong'    => array(),
                     'sub'       => array(),
                     'sup'       => array(),
                     'var'       => array(),
                    );





  // constructor
  function TexyHTMLTagModule(&$texy)
  {
    parent::TexyModule($texy);

    $this->allowed = $texy->validElements;
  }





  /***
   * Module initialization.
   */
  function init()
  {
    $HASH = TEXY_HASH;
    $this->registerLinePattern('processLine', "#<(/?)([a-z][a-z0-9_:-]*)(|\s(?:[\sa-z0-9-]|=\s*\"[^\"$HASH]*\"|=\s*'[^'$HASH]*'|=[^>$HASH]*)*)(/?)>#is");
  }



  /***
   * Callback function: <tag ...>
   * @return string
   */
  function processLine(&$lineParser, &$matches)
  {
    list($match, $mClosing, $mTag, $mAttr, $mEmpty) = $matches;
    //    [1] => /
    //    [2] => tag
    //    [3] => attributes

    if (!$this->allowed) return $match;   // disabled

    $tag = strtolower($mTag);
    $empty = $mEmpty == '/';
    $closing = $mClosing == '/';
    $classify = Texy::classifyElement($tag);
    if ($classify & TEXY_ELEMENT_VALID) {
      $empty = $classify & TEXY_ELEMENT_EMPTY;
    } else {
      $tag = $mTag;  // undo lowercase
    }

    if ($empty && $closing)  // error - can't close empty element
      return $match;


    if (!$closing) {
      $attr = array();
      preg_match_all('#([a-z0-9-]+)\s*(?:=\s*(\'[^\']*\'|"[^"]*"|[^\'"\s]+))?()#is', $mAttr, $matchesAttr, PREG_SET_ORDER);
      foreach ($matchesAttr as $matchAttr) {
        $key = strtolower($matchAttr[1]);
        $value = $matchAttr[2];
        if (!$value) $value = $key;
        elseif ($value{0} == '\'' || $value{0} == '"') $value = substr($value, 1, -1);
        $attr[$key] = $value;
      }
    } else {
      $attr = false;
    }

/*
    if ($this->userFunction)  // call user function?
      call_user_func_array(
            $this->userFunction,
            array(&$this->texy, &$tag, &$attr)
      );
    if (!$tag) return $match;
*/

    if (is_array($this->allowed)) {
      if (!$this->isAllowed($tag))
        return $match;

      $allowedAttrs = $this->allowed[$tag];
      if (!$closing && is_array($allowedAttrs))
        foreach ($attr as $key => $value)
          if (!in_array($key, $allowedAttrs)) unset($attr[$key]);
    }



    if (!$closing) {

      // apply allowedClasses & allowedStyles
      $modifier = & $this->texy->createModifier();

      if (isset($attr['class'])) $modifier->parseClasses($attr['class']);
      $attr['class'] = $modifier->implodeClasses( $modifier->classes );

      if (isset($attr['style'])) $modifier->parseStyles($attr['style']);
      $attr['style'] = $modifier->implodeStyles( $modifier->styles );

      if (isset($attr['id'])) {
        if (!$this->texy->allowedClasses)
          unset($attr['id']);
        elseif (is_array($this->texy->allowedClasses) && !in_array('#'.$attr['id'], $this->texy->allowedClasses))
          unset($attr['id']);
      }




      switch ($tag) {
       case 'img':
          if (!isset($attr['src'])) return $match;
          $link = &$this->texy->createURL();
          $link->set($attr['src'], TEXY_URL_IMAGE_INLINE);
          $this->texy->summary->images[] = $attr['src'] = $link->URL;
          break;

       case 'a':
          if (!isset($attr['href']) && !isset($attr['name']) && !isset($attr['id'])) return $match;
          if (isset($attr['href'])) {
            $link = &$this->texy->createURL();
            $link->set($attr['href']);
            $this->texy->summary->links[] = $attr['href'] = $link->URL;
          }
      }
    }

    $el = &new TexySingleTagElement($this->texy);
    $el->attr     = $attr;
    $el->tag      = $tag;
    $el->closing  = $closing;
    $el->empty    = $empty;
    $el->strength = ($classify & TEXY_ELEMENT_INLINE) ? TEXY_SOFT : TEXY_HARD;

    return $el->addTo($lineParser->element);
  }




  function trustMode($onlyValidTags = true)
  {
    $this->allowed = $onlyValidTags ? $this->texy->validElements : true;
  }



  function safeMode($allowSafeTags = true)
  {
    $this->allowed = $allowSafeTags ? $this->safeTags : false;
  }



} // TexyHTMLTagModule









/****************************************************************************
                               TEXY! DOM ELEMENTS                          */




class TexySingleTagElement extends TexyDOMElement {
  var $tag;
  var $attr;
  var $closing;
  var $empty;
  var $strength = TEXY_SOFT;



  // convert element to HTML string
  function toHTML()
  {
    if ($this->hidden) return;

    if ($this->empty || !$this->closing)
      return Texy::openingTag($this->tag, $this->attr, $this->empty);
    else
      return Texy::closingTag($this->tag);
  }



  function addTo(&$lineElement)
  {
    $key = Texy::hashKey($this->strength);
    $lineElement->children[$key]  = array(&$this, null);
    if ($this->strength == TEXY_HARD)
      $lineElement->contentType = max($lineElement->contentType, TEXY_CONTENT_HTML);
    return $key;
  }


}  // TexySingleTagElement















/**
 * IMAGES MODULE CLASS
 */
class TexyImageModule extends TexyModule {
  // options
  var $root       = 'images/';     // root of relative images (http)
  var $linkedRoot = 'images/';     // root of linked images (http)
  var $rootPrefix = '';            // physical location on server
  var $leftClass  = '';            // left-floated image class
  var $rightClass = '';            // right-floated image class
  var $defaultAlt = 'image';       // default image alternative text

  // private
  var $references  = array();      // references: 'home' => TexyImageReference
  var $userReferences;             // function &myUserFunc(&$texy, $refName): returns TexyImageReference (or false)
  var $_backupReferences;




  /***
   * Module initialization.
   */
  function init()
  {
    Texy::adjustDir($this->root);
    Texy::adjustDir($this->linkedRoot);
    Texy::adjustDir($this->rootPrefix);

    // [*image*]:LINK    where LINK is:   url | [ref] | [*image*]
    $this->registerLinePattern('processLine',     '#'.TEXY_PATTERN_IMAGE.TEXY_PATTERN_LINK_N.'?()#U');
  }




  /***
   * Add new named image
   */
  function addReference($name, &$obj)
  {
    $name = strtolower($name);
    $this->references[$name] = &$obj;
  }




  /***
   * Receive new named link. If not exists, try
   * call user function to create one.
   */
  function &getReference($name)
  {
    $name = strtolower($name);

    if (isset($this->references[$name]))
      return $this->references[$name];


    if ($this->userReferences) {
      $obj = &call_user_func_array(
                   $this->userReferences,
                   array(&$this->texy, $name)
      );

      if ($obj) {
        $this->references[$name] = & $obj; // save for next time
        return $obj;
      }
    }

    return false;
  }




  /***
   * Forget all references created during last parse()
   */
  function forgetReferences()
  {
    $this->references = $this->_backupReferences;
  }







  /***
   * Preprocessing
   */
  function preProcess(&$text)
  {
    $this->_backupReferences = $this->references;

    // [*image*]: urls .(title)[class]{style}
    $text = preg_replace_callback('#^\[\*([^\n]+)\*\]:\ +(.+)\ *'.TEXY_PATTERN_MODIFIER.'?()$#mU', array(&$this, '_replaceReference'), $text);
  }



  /***
   * Callback function: [*image*]: urls .(title)[class]{style}
   * @return string
   */
  function _replaceReference(&$matches)
  {
    list($match, $mRef, $mUrls, $mMod1, $mMod2, $mMod3) = $matches;
    //    [1] => [* (reference) *]
    //    [2] => urls
    //    [3] => (title)
    //    [4] => [class]
    //    [5] => {style}

    $elRef = &new TexyImageReference($this->texy, $mUrls);
    $elRef->modifier->setProperties($mMod1, $mMod2, $mMod3);

    $this->addReference($mRef, $elRef);

    return '';
  }






  /***
   * Callback function: [* texy.gif *]: small.jpg | small-over.jpg | big.jpg .(alternative text)[class]{style}>]:LINK
   * @return string
   */
  function processLine(&$lineParser, &$matches)
  {
    if (!$this->allowed) return '';
    list($match, $mURLs, $mMod1, $mMod2, $mMod3, $mMod4, $mLink) = $matches;
    //    [1] => URLs
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => >
    //    [6] => url | [ref] | [*image*]

    $elImage = &new TexyImageElement($this->texy);
    $elImage->setImagesRaw($mURLs);
    $elImage->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

    if ($mLink) {
      $elLink = &new TexyLinkElement($this->texy);
      if ($mLink == ':') {
        $elImage->requireLinkImage();
        $elLink->link->copyFrom($elImage->linkImage);
      } else {
        $elLink->setLinkRaw($mLink);
      }

      return $elLink->addTo(
                         $lineParser->element,
                         $elImage->addTo($lineParser->element)
                      );
    }

    return $elImage->addTo($lineParser->element);
  }





} // TexyImageModule






class TexyImageReference {
  var $URLs;
  var $modifier;


  // constructor
  function TexyImageReference(&$texy, $URLs = null)
  {
    $this->modifier = & $texy->createModifier();
    $this->URLs = $URLs;
  }

}






/****************************************************************************
                               TEXY! DOM ELEMENTS                          */






/**
 * HTML ELEMENT IMAGE
 */
class TexyImageElement extends TexyTextualElement {
  var $parentModule;

  var $image;
  var $overImage;
  var $linkImage;

  var $width, $height;


  // constructor
  function TexyImageElement(&$texy)
  {
    parent::TexyTextualElement($texy);
    $this->parentModule = & $texy->modules['TexyImageModule'];

    $this->image = & $texy->createURL();
    $this->image->root = $this->parentModule->root;

    $this->overImage = & $texy->createURL();
    $this->overImage->root = $this->parentModule->root;

    $this->linkImage = & $texy->createURL();
    $this->linkImage->root = $this->parentModule->linkedRoot;
  }



  function setImages($URL = null, $URL_over = null, $URL_link = null)
  {
    if ($URL)
      $this->image->set($URL, TEXY_URL_IMAGE_INLINE);
    else
      $this->image->clear();

    if ($URL_over)
      $this->overImage->set($URL_over, TEXY_URL_IMAGE_INLINE);
    else
      $this->overImage->clear();

    if ($URL_link)
      $this->linkImage->set($URL_link, TEXY_URL_IMAGE_LINKED);
    else
      $this->linkImage->clear();
  }



  function setSize($width, $height)
  {
    $width = abs((int) $width);
    $height = abs((int) $height);

    if ($width && $height) {
      $this->width = $width;
      $this->height = $height;
    } else {
      $this->width = $this->height = null;
    }
  }


  // private
  function setImagesRaw($URLs)
  {
    $elRef = &$this->parentModule->getReference(trim($URLs));
    if ($elRef) {
      $URLs = $elRef->URLs;
      $this->modifier->copyFrom($elRef->modifier);
    }

    $URLs = explode('|', $URLs . '||');

    // dimensions
    if (preg_match('#^(.*) (\d+) *x *(\d+) *()$#U', $URLs[0], $matches)) {
      $URLs[0] = $matches[1];
      $this->setSize($matches[2], $matches[3]);
    }

    $this->setImages($URLs[0], $URLs[1], $URLs[2]);
  }




  function generateTag(&$tag, &$attr)
  {
    if (!$this->image->URL) return;  // image URL is required

    $tag = 'img';

    // classes & styles
    $classes = $this->modifier->classes;
    $styles = $this->modifier->styles;

    if ($this->modifier->hAlign == TEXY_HALIGN_LEFT) {
      if ($this->parentModule->leftClass)
        $classes[] = $this->parentModule->leftClass;
      else
        $styles['float'] = 'left';

    } elseif ($this->modifier->hAlign == TEXY_HALIGN_RIGHT)  {

      if ($this->parentModule->rightClass)
        $classes[] = $this->parentModule->rightClass;
      else
        $styles['float'] = 'right';
    }

    $styles['vertical-align'] = $this->modifier->vAlign;
    $attr['class'] = TexyModifier::implodeClasses($classes);
    $attr['style'] = TexyModifier::implodeStyles($styles);
    $attr['id'] = $this->modifier->id;

    // width x height generate
    $this->requireSize();
    $attr['width'] = $this->width;
    $attr['height'] = $this->height;

    // attribute generate
    $this->texy->summary->images[] = $attr['src'] = $this->image->URL;

    // onmouseover actions generate
    if ($this->overImage->URL) {
      $attr['onmouseover'] = 'this.src=\''.$this->overImage->URL.'\'';
      $attr['onmouseout'] = 'this.src=\''.$this->image->URL.'\'';
      $this->texy->summary->preload[] = $this->overImage->URL;
    }

    // alternative text generate
    $attr['alt'] = $this->modifier->title ? $this->modifier->title : $this->parentModule->defaultAlt;
  }



  function requireSize()
  {
    if ($this->width) return;

    $file = $this->parentModule->rootPrefix . $this->image->URL;
    if (!is_file($file)) return false;

    $size = getImageSize($file);
    if (!is_array($size)) return false;

    $this->setSize($size[0], $size[1]);
  }


  function requireLinkImage()
  {
    if (!$this->linkImage->URL)
      $this->linkImage->set($this->image->text, TEXY_URL_IMAGE_LINKED);
  }



} // TexyImages














/**
 * IMAGE WITH DESCRIPTION MODULE CLASS
 */
class TexyImageDescModule extends TexyModule {
  var $boxClass   = 'image';        // non-floated box class
  var $leftClass  = 'image left';   // left-floated box class
  var $rightClass = 'image right';  // right-floated box class

  /***
   * Module initialization.
   */
  function init()
  {
    if ($this->allowed)
      $this->registerBlockPattern('processBlock', '#^'.TEXY_PATTERN_IMAGE.TEXY_PATTERN_LINK_N.'? +\*\*\* +(.*)MODIFIER_H?()$#mU');
  }



  /***
   * Callback function (for blocks)
   *
   *            [*image*]:link *** .... .(title)[class]{style}>
   *
   */
  function processBlock(&$blockParser, &$matches)
  {
    list($match, $mURLs, $mImgMod1, $mImgMod2, $mImgMod3, $mImgMod4, $mLink, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
    //    [1] => URLs
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => >
    //    [6] => url | [ref] | [*image*]
    //    [7] => ...
    //    [8] => (title)
    //    [9] => [class]
    //    [10] => {style}
    //    [11] => >

    $el = &new TexyImageDescElement($this->texy);
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

    if ($this->texy->images->allowed) {
      $el->children['img']->setImagesRaw($mURLs);
      $el->children['img']->modifier->setProperties($mImgMod1, $mImgMod2, $mImgMod3, $mImgMod4);
      $el->modifier->hAlign = $el->children['img']->modifier->hAlign;
      $el->children['img']->modifier->hAlign = null;
    }

    $el->children['desc']->parse(ltrim($mContent));

    $blockParser->addChildren($el);
  }




} // TexyImageModule










/****************************************************************************
                               TEXY! DOM ELEMENTS                          */







/**
 * HTML ELEMENT IMAGE (WITH DESCRIPTION)
 */
class TexyImageDescElement extends TexyBlockElement {
  var $parentModule;


  // constructor
  function TexyImageDescElement(&$texy)
  {
    parent::TexyBlockElement($texy);
    $this->parentModule = & $texy->modules['TexyImageDescModule'];

    $this->children['img'] = &new TexyImageElement($texy);
    $this->children['desc'] = &new TexyGenericBlockElement($texy);
  }



  function generateTag(&$tag, &$attr)
  {
    $tag = 'div';

    $classes = $this->modifier->classes;
    $styles = $this->modifier->styles;

    if ($this->modifier->hAlign == TEXY_HALIGN_LEFT) {
      $classes[] = $this->parentModule->leftClass;

    } elseif ($this->modifier->hAlign == TEXY_HALIGN_RIGHT)  {
      $classes[] = $this->parentModule->rightClass;

    } elseif ($this->parentModule->boxClass)
      $classes[] = $this->parentModule->boxClass;

    $attr['class'] = TexyModifier::implodeClasses($classes);
    $attr['style'] = TexyModifier::implodeStyles($styles);
    $attr['id'] = $this->modifier->id;
  }



}  // TexyImageDescElement









/**
 * LINKS MODULE CLASS
 */
class TexyLinkModule extends TexyModule {
  // options
  var $allowed         = array(                       // generally disable / enable links
          'link'      => true,                        // classic link "xxx":url
          'reference' => true,                        // [reference]: url
          'email'     => true,                        // emails replacement
          'url'       => true);                       // direct url replacement
  var $root            = '';                          // root of relative links
  var $emailOnClick    = '';                          // 'this.href="mailto:"+this.href.match(/./g).reverse().slice(0,-7).join("")';
  var $imageOnClick    = 'return !popup(this.href)';  // image popup event
  var $forceNoFollow   = false;                       // always use rel="nofollow" for absolute links

  // private
  var $references      = array();                     // references: 'home' => TexyLinkReference
  var $userReferences;                                // function &myUserFunc(&$texy, $refName): returns TexyLinkReference (or false)
  var $imageModuleName = 'images';                    // $texy->modules[NAME]
  var $_disableRefs    = false;                       // prevent recurse calling
  var $_backupReferences;



  /***
   * Module initialization.
   */
  function init()
  {
    Texy::adjustDir($this->root);

    // "... .(title)[class]{style}":LINK    where LINK is:   url | [ref] | [*image*]
    $this->registerLinePattern('processLineQuot',      '#(?<!\")\"(?!\ )([^\n\"]+)MODIFIER?(?<!\ )\"'.TEXY_PATTERN_LINK.'()#U');
    $this->registerLinePattern('processLineQuot',      '#(?<!\~)\~(?!\ )([^\n\~]+)MODIFIER?(?<!\ )\~'.TEXY_PATTERN_LINK.'()#U');

    // [reference]
    if ($this->isAllowed('reference')) {
      $this->registerLinePattern('processLineReference', '#('.TEXY_PATTERN_LINK_REF.')#U');
    }

    // direct url and email
    if ($this->isAllowed('url'))
      $this->registerLinePattern('processLineURL',       '#(?<=\s|^|\(|\[|\<|:)(?:https?://|www\.|ftp://|ftp\.)[a-z0-9.-][/a-z\d+\.~%&?@=_:;\#,-]+[/\w\d+~%?@=_\#]#i'.TEXY_PATTERN_UTF);
    if ($this->isAllowed('email'))
      $this->registerLinePattern('processLineURL',       '#(?<=\s|^|\(|\[|\<|:)'.TEXY_PATTERN_EMAIL.'#i');
  }




  /***
   * Add new named link
   */
  function addReference($name, &$obj)
  {
    $name = strtolower($name);
    $this->references[$name] = &$obj;
  }




  /***
   * Receive new named link. If not exists, try
   * call user function to create one.
   */
  function &getReference($name)
  {
    if ($this->_disableRefs) return false;

    $name = strtolower($name);

    if (isset($this->references[$name]))
      return $this->references[$name];


    $queryPos = strpos($name, '?');
    if ($queryPos === false) $queryPos = strpos($name, '#');
    if ($queryPos !== false) { // try to extract ?... #... part
      $nameX = substr($name, 0, $queryPos);

      if (isset($this->references[$nameX])) {
        $obj = clone ($this->references[$nameX]);
        unset($obj->modifier); // for PHP4
        $obj->modifier = clone ($this->references[$nameX]->modifier);
        $obj->URL .= substr($name, $queryPos);
        return $obj;
      }
    }

    if ($this->userReferences) {
      $this->_disableRefs = true;
      $obj = &call_user_func_array(
                   $this->userReferences,
                   array(&$this->texy, $name)
      );
      $this->_disableRefs = false;

      if ($obj) {
        $this->references[$name] = & $obj; // save for next time
        return $obj;
      }
    }

    return false;
  }




  /***
   * Forget all references created during last parse()
   */
  function forgetReferences()
  {
    $this->references = $this->_backupReferences;
  }








  /***
   * Preprocessing
   */
  function preProcess(&$text)
  {
    $this->_backupReferences = $this->references;

    // [la trine]: http://www.dgx.cz/trine/ text odkazu .(title)[class]{style}
    $text = preg_replace_callback('#^\[([^\[\]\#\?\*\n]+)\]: +('.TEXY_PATTERN_LINK_IMAGE.'|(?-U)(?!\[)\S+(?U))(\ .+)?\ *'.TEXY_PATTERN_MODIFIER.'?()$#mU', array(&$this, '_replaceReference'), $text);
  }




  /***
   * Callback function: [la trine]: http://www.dgx.cz/trine/ text odkazu .(title)[class]{style}
   * @return string
   */
  function _replaceReference(&$matches)
  {
    list($match, $mRef, $mLink, $mLabel, $mMod1, $mMod2, $mMod3) = $matches;
    //    [1] => [ (reference) ]
    //    [2] => link
    //    [3] => ...
    //    [4] => (title)
    //    [5] => [class]
    //    [6] => {style}

    $elRef = &new TexyLinkReference($this->texy, $mLink, $mLabel);
    $elRef->modifier->setProperties($mMod1, $mMod2, $mMod3);

    $this->addReference($mRef, $elRef);

    return '';
  }






  /***
   * Callback function: ".... (title)[class]{style}<>":LINK
   * @return string
   */
  function processLineQuot(&$lineParser, &$matches)
  {
    list($match, $mContent, $mMod1, $mMod2, $mMod3, $mLink) = $matches;
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => url | [ref] | [*image*]

    if (!$this->isAllowed('link')) return $mContent;

    $elLink = &new TexyLinkElement($this->texy);
    $elLink->setLinkRaw($mLink);
    $elLink->modifier->setProperties($mMod1, $mMod2, $mMod3);
    return $elLink->addTo($lineParser->element, $mContent);
  }





  /***
   * Callback function: [ref]
   * @return string
   */
  function processLineReference(&$lineParser, &$matches)
  {
    list($match, $mRef) = $matches;
    //    [1] => [ref]

    $elLink = &new TexyLinkRefElement($this->texy);
    if ($elLink->setLink($mRef) === false) return $match;

    return $elLink->addTo($lineParser->element);
  }




  /***
   * Callback function: http://www.dgx.cz
   * @return string
   */
  function processLineURL(&$lineParser, &$matches)
  {
    list($mURL) = $matches;
    //    [0] => URL

    $elLink = &new TexyLinkElement($this->texy);
    $elLink->setLinkRaw($mURL);
    return $elLink->addTo($lineParser->element, $elLink->link->toString());
  }




} // TexyLinkModule






class TexyLinkReference {
  var $URL;
  var $label;
  var $modifier;


  // constructor
  function TexyLinkReference(&$texy, $URL = null, $label = null)
  {
    $this->modifier = & $texy->createModifier();

    if (strlen($URL) > 1)  if ($URL{0} == '\'' || $URL{0} == '"') $URL = substr($URL, 1, -1);
    $this->URL = $URL;
    $this->label = $label;
  }

}






/****************************************************************************
                               TEXY! DOM ELEMENTS                          */



/**
 * HTML TAG ANCHOR
 */
class TexyLinkElement extends TexyInlineTagElement {
  var $parentModule;
  var $link;
  var $nofollow = false;


  // constructor
  function TexyLinkElement(&$texy)
  {
    parent::TexyInlineTagElement($texy);
    $this->parentModule = & $texy->modules['TexyLinkModule'];

    $this->link = & $texy->createURL();
    $this->link->root = $this->parentModule->root;
  }


  function setLink($URL)
  {
    $this->link->set($URL);
  }


  function setLinkRaw($link)
  {
    if (@$link{0} == '[' && @$link{1} != '*') {
      $elRef = & $this->parentModule->getReference( substr($link, 1, -1) );
      if ($elRef) {
        $this->modifier->copyFrom($elRef->modifier);
        $link = $elRef->URL;

      } else {
        $this->setLink(substr($link, 1, -1));
        return;
      }
    }

    $l = strlen($link);
    if (@$link{0} == '[' && @$link{1} == '*') {
      $elImage = &new TexyImageElement($this->texy);
      $elImage->setImagesRaw(substr($link, 2, -2));
      $elImage->requireLinkImage();
      $this->link->copyFrom($elImage->linkImage);
      return;
    }

    $this->setLink($link);
  }




  function generateTag(&$tag, &$attr)
  {
    if (!$this->link->URL) return;  // image URL is required

    $tag  = 'a';

    $this->texy->summary->links[] = $attr['href'] = $this->link->URL;

    // rel="nofollow"
    $nofollowClass = in_array('nofollow', $this->modifier->unfilteredClasses);
    if (($this->link->type & TEXY_URL_ABSOLUTE) && ($nofollowClass || $this->nofollow || $this->parentModule->forceNoFollow))
      $attr['rel'] = 'nofollow';

    $attr['id']    = $this->modifier->id;
    $attr['title'] = $this->modifier->title;
    $classes = $this->modifier->classes;
    if ($nofollowClass) {
      $nofollowClass = array_search('nofollow', $classes);
      if ($nofollowClass !== false) unset($classes[$nofollowClass]);
    }
    $attr['class'] = TexyModifier::implodeClasses($classes);

    $styles = $this->modifier->styles;
    $attr['style'] = TexyModifier::implodeStyles($styles);

    // email on click
    if ($this->link->type & TEXY_URL_EMAIL)
      $attr['onclick'] = $this->parentModule->emailOnClick;

    // image on click
    if ($this->link->type & TEXY_URL_IMAGE_LINKED)
      $attr['onclick'] = $this->parentModule->imageOnClick;
  }


} // TexyLinkElement









/**
 * HTML ELEMENT ANCHOR (with content)
 */
class TexyLinkRefElement extends TexyTextualElement {
  var $parentModule;
  var $tag = 'a';
  var $link;
  var $nofollow = false;
  var $refName;
  var $contentType = TEXY_CONTENT_TEXTUAL;

  // private


  // constructor
  function TexyLinkRefElement(&$texy)
  {
    parent::TexyTextualElement($texy);
    $this->parentModule = & $texy->modules['TexyLinkModule'];

    $this->link = & $texy->createURL();
    $this->link->root = $this->parentModule->root;
  }



  function setLink($refName)
  {
    $elRef = & $this->parentModule->getReference( substr($refName, 1, -1) );
    if (!$elRef) return false;

    $this->refName = $refName;
    $this->modifier->copyFrom($elRef->modifier);

    $this->parentModule->_disableRefs = true;
    $this->parse($elRef->label);
    $this->parentModule->_disableRefs = false;

    if (@$elRef->URL{0} == '[' && @$elRef->URL{1} == '*') {
      $elImage = &new TexyImageElement($this->texy);
      $elImage->setImagesRaw(substr($elRef->URL, 2, -2));
      $elImage->requireLinkImage();
      $this->link->copyFrom($elImage->linkImage);
      return;
    }

    $this->link->set($elRef->URL);
  }





  function generateTag(&$tag, &$attr)
  {
    if (!$this->link->URL) return;  // image URL is required

    $tag  = 'a';

    $this->texy->summary->links[] = $attr['href'] = $this->link->URL;

    // rel="nofollow"
    $nofollowClass = in_array('nofollow', $this->modifier->unfilteredClasses);
    if (($this->link->type & TEXY_URL_ABSOLUTE) && ($nofollowClass || $this->nofollow || $this->parentModule->forceNoFollow))
      $attr['rel'] = 'nofollow';

    $attr['id']    = $this->modifier->id;
    $attr['title'] = $this->modifier->title;
    $classes = $this->modifier->classes;
    if ($nofollowClass) {
      $nofollowClass = array_search('nofollow', $classes);
      if ($nofollowClass !== false) unset($classes[$nofollowClass]);
    }
    $attr['class'] = TexyModifier::implodeClasses($classes);

    $styles = $this->modifier->styles;
    $attr['style'] = TexyModifier::implodeStyles($styles);

    // email on click
    if ($this->link->type & TEXY_URL_EMAIL)
      $attr['onclick'] = $this->parentModule->emailOnClick;

    // image on click
    if ($this->link->type & TEXY_URL_IMAGE_LINKED)
      $attr['onclick'] = $this->parentModule->imageOnClick;
  }





  function generateContent()
  {
    if ($this->content)
      return parent::generateContent();
    else
      return Texy::htmlChars($this->link->toString());
  }

} // TexyLinkRefElement














/**
 * LONG WORDS WRAP MODULE CLASS
 */
class TexyLongWordsModule extends TexyModule {
  var $wordLimit = 20;
  var $shy       = '&shy;';
  var $nbsp      = '&nbsp;';
  var $charShy;
  var $charNbsp;



  // constructor
  function TexyLongWordsModule(&$texy)
  {
    parent::TexyModule($texy);

    $this->charShy = TEXY_UTF8 ? "\xC2\xAD" : "\xAD";
    $this->charNbsp = TEXY_UTF8 ? "\xC2\xA0" : "\xA0";
  }



  function linePostProcess(&$text)
  {
    if (!$this->allowed) return;
    $text = strtr($text, array(
                            '&shy;'  => $this->charShy,
                            '&nbsp;' => $this->charNbsp,
                         ));

    $text = preg_replace_callback('#[^\ \n\t\-\xAD'.TEXY_HASH_SPACES.']{'.$this->wordLimit.',}#'.TEXY_PATTERN_UTF, array(&$this, '_replace'), $text);

    $text = strtr($text, array(
                            $this->charShy  => $this->shy,
                            $this->charNbsp => $this->nbsp,
                         ));
  }


  /***
   * Callback function: rozdìlí dlouhá slova na slabiky - EXPERIMENTÁLNÍ
   * (c) David Grudl
   * @return string
   */
  function _replace(&$matches)
  {
    list($mWord) = $matches;
    //    [0] => lllloooonnnnggggwwwoorrdddd

    $chars = array();
    preg_match_all('#&\\#?[a-z0-9]+;|['.TEXY_HASH.']+|.#'.TEXY_PATTERN_UTF, $mWord, $chars);
    $chars = $chars[0];
    if (count($chars) < $this->wordLimit) return $mWord;

        // little trick - isset($array[$item]) is much faster than in_array($item, $array)
    $consonants = array_flip(array(
                        'b','c','d','f','g','h','j','k','l','m','n','p','q','r','s','t','v','w','x','y','z',
                        'B','C','D','F','G','H','J','K','L','M','N','P','Q','R','S','T','V','W','X','Y','Z',
                        'è','ï','ò','ø','š','','ý','ž',            //czech windows-1250
                        'È','Ï','Ò','Ø','Š','','Ý','Ž',
                        'Ä','Ä','Åˆ','Å™','Å¡','Å¥','Ã½','Å¾',    //czech utf-8
                        'ÄŒ','ÄŽ','Å‡','Å˜','Å ','Å¤','Ã','Å½'));
    $vowels     = array_flip(array(
                        'a','e','i','o','y','u',
                        'A','E','I','O','Y','U',
                        'á','é','ì','í','ó','ý','ú','ù',            //czech windows-1250
                        'Á','É','Ì','Í','Ó','Ý','Ú','Ù',
                        'Ã¡','Ã©','Ä›','Ã­','Ã³','Ã½','Ãº','Å¯',    //czech utf-8
                        'Ã','Ã‰','Äš','Ã','Ã“','Ã','Ãš','Å®'));

    $before_r   = array_flip(array(
                        'b','B','c','C','d','D','f','F','g','G','k','K','p','P','r','R','t','T','v','V',
                        'è','È','ï','Ï','ø','Ø','','',                  //czech windows-1250
                        'Ä','ÄŒ','Ä','ÄŽ','Åt','Å_','Å¥','Å¤',          //czech utf-8
                        ));

    $before_l   = array_flip(array(
                        'b','B','c','C','d','D','f','F','g','G','k','K','l','L','p','P','t','T','v','V',
                        'è','È','ï','Ï','','',                          //czech windows-1250
                        'Ä','ÄŒ','Ä','ÄŽ','Å¥','Å¤',                    //czech utf-8
                        ));

    $before_h   = array_flip(array('c', 'C', 's', 'S'));

    $doubleVowels = array_flip(array('a', 'A','o', 'O'));

    // consts
    $DONT = 0;   // don't hyphenate
    $HERE = 1;   // hyphenate here
    $AFTER = 2;  // hyphenate after

    $s = array();
    $trans = array();

    $s[] = '';
    $trans[] = -1;
    $hashCounter = $len = $counter = 0;
    foreach ($chars as $key => $char) {
      if (ord($char{0}) < 32) continue;
      $s[] = $char;
      $trans[] = $key;
    }
    $s[] = '';
    $len = count($s) - 2;

    $positions = array();
    $a = 1; $last = 1;

    while ($a < $len) {
      $hyphen = $DONT; // Do not hyphenate
      do {
        if ($s[$a] == '.') { $hyphen = $HERE; break; }   // ???

        if (isset($consonants[$s[$a]])) {  // souhlásky

          if (isset($vowels[$s[$a+1]])) {
            if (isset($vowels[$s[$a-1]])) $hyphen = $HERE;
            break;
          }

          if (($s[$a] == 's') && ($s[$a-1] == 'n') && isset($consonants[$s[$a+1]])) { $hyphen = $AFTER; break; }

          if (isset($consonants[$s[$a+1]]) && isset($vowels[$s[$a-1]])) {
            if ($s[$a+1] == 'r') {
              $hyphen = isset($before_r[$s[$a]]) ? $HERE : $AFTER;
              break;
            }

            if ($s[$a+1] == 'l') {
              $hyphen = isset($before_l[$s[$a]]) ? $HERE : $AFTER;
              break;
            }

            if ($s[$a+1] == 'h') { // CH
              $hyphen = isset($before_h[$s[$a]]) ? $DONT : $AFTER;
              break;
            }

            $hyphen = $AFTER;
            break;
          }

          break;
        }   // konec souhlasky


        if (($s[$a] == 'u') && isset($doubleVowels[$s[$a-1]])) { $hyphen = $AFTER; break; }
        if (in_array($s[$a], $vowels) && isset($vowels[$s[$a-1]])) { $hyphen = $HERE; break; }

      } while(0);

      if ($hyphen == $DONT && ($a - $last > $this->wordLimit*0.6)) $positions[] = $last = $a-1; // Hyphenate here
      if ($hyphen == $HERE) $positions[] = $last = $a-1; // Hyphenate here
      if ($hyphen == $AFTER) { $positions[] = $last = $a; $a++; } // Hyphenate after

      $a++;
    } // while


    $a = end($positions);
    if (($a == $len-1) && isset($consonants[$s[$len]]))
      array_pop($positions);


    $syllables = array();
    $last = 0;
    foreach ($positions as $pos) {
      if ($pos - $last > $this->wordLimit*0.6) {
        $syllables[] = implode('', array_splice($chars, 0, $trans[$pos] - $trans[$last]));
        $last = $pos;
      }
    }
    $syllables[] = implode('', $chars);

    $text = implode($this->charShy, $syllables);
    $text = strtr($text, array($this->charShy.$this->charNbsp => ' ', $this->charNbsp.$this->charShy => ' '));

    return $text;
  }




} // TexyLongWordsModule










/**
 * PHRASES MODULE CLASS
 *
 *   **strong**
 *   *emphasis*
 *   ***strong+emphasis***
 *   ^^superscript^^
 *   __subscript__
 *   ++inserted++
 *   --deleted--
 *   ~~cite~~
 *   "span"
 *   ~span~
 *   `....`
 *   ``....``
 */
class TexyPhrasesModule extends TexyModule {
   var $allowed = array('***' => 'strong em',
                        '**'  => 'strong',
                        '*'   => 'em',
                        '++'  => 'ins',
                        '--'  => 'del',
                        '^^'  => 'sup',
                        '__'  => 'sub',
                        '"'   => 'span',
                        '~'   => 'span',
                        '~~'  => 'cite',
                        '()'  => 'acronym',
                        '`'   => 'code',
                        '``'  => '',
                        );
   var $codeUserFunction;  // function &myUserFunc(&$element)




  /***
   * Module initialization.
   */
  function init()
  {
    $CHAR = '['.TEXY_CHAR.']';

    // strong & em speciality *** ... ***
    if ($this->isAllowed('***'))
      $this->registerLinePattern('processPhrase',  '#(?<!\*)\*\*\*(?!\ |\*)(.+)MODIFIER?(?<!\ |\*)\*\*\*(?!\*)()#U',   $this->allowed['***']);

    // **strong**
    if ($this->isAllowed('**'))
      $this->registerLinePattern('processPhrase',  '#(?<!\*)\*\*(?!\ |\*)(.+)MODIFIER?(?<!\ |\*)\*\*(?!\*)()#U',       $this->allowed['**']);

    // *emphasis*
    if ($this->isAllowed('*'))
      $this->registerLinePattern('processPhrase',  '#(?<!\*)\*(?!\ |\*)(.+)MODIFIER?(?<!\ |\*)\*(?!\*)()#U',           $this->allowed['*']);

    // ++inserted++
    if ($this->isAllowed('++'))
      $this->registerLinePattern('processPhrase',  '#(?<!\+)\+\+(?!\ |\+)(.+)MODIFIER?(?<!\ |\+)\+\+(?!\+)()#U',       $this->allowed['++']);

    // --deleted--
    if ($this->isAllowed('--'))
      $this->registerLinePattern('processPhrase',  '#(?<!\-)\-\-(?!\ |\-)(.+)MODIFIER?(?<!\ |\-)\-\-(?!\-)()#U',       $this->allowed['--']);

    // ^^superscript^^
    if ($this->isAllowed('^^'))
      $this->registerLinePattern('processPhrase',  '#(?<!\^)\^\^(?!\ |\^)(.+)MODIFIER?(?<!\ |\^)\^\^(?!\^)()#U',       $this->allowed['^^']);

    // __subscript__
    if ($this->isAllowed('__'))
      $this->registerLinePattern('processPhrase',  '#(?<!\_)\_\_(?!\ |\_)(.+)MODIFIER?(?<!\ |\_)\_\_(?!\_)()#U',       $this->allowed['__']);

    // "span"
    if ($this->isAllowed('"'))
      $this->registerLinePattern('processPhrase',  '#(?<!\")\"(?!\ )([^\"]+)MODIFIER(?<!\ )\"(?!\"|\:\S)()#U',         $this->allowed['"']);

    // ~alternative span~
    if ($this->isAllowed('~'))
      $this->registerLinePattern('processPhrase',  '#(?<!\~)\~(?!\ )([^\~]+)MODIFIER(?<!\ )\~(?!\~|\:\S)()#U',         $this->allowed['~']);

    // ~~cite~~
    if ($this->isAllowed('~~'))
      $this->registerLinePattern('processPhrase',  '#(?<!\~)\~\~(?!\ |\~)(.+)MODIFIER?(?<!\ |\~)\~\~(?!\~)()#U',       $this->allowed['~~']);

    if ($this->isAllowed('()')) {
      // acronym/abbr "et al."((and others))
      $this->registerLinePattern('processPhrase',  '#(?<!\")\"(?!\ )([^\"]+)MODIFIER?(?<!\ )\"(?!\")\(\((.+)\)\)()#U', $this->allowed['()']);
      // acronym/abbr NATO((North Atlantic Treaty Organisation))
      $this->registerLinePattern('processPhrase',  "#(?<!$CHAR)($CHAR{2,})()()()\(\((.+)\)\)#U".TEXY_PATTERN_UTF,      $this->allowed['()']);
    }

    // ``protected`` (experimental, dont use)
    if ($this->isAllowed('``'))
      $this->registerLinePattern('processProtect', '#\`\`(\S[^'.TEXY_HASH.']*)(?<!\ )\`\`()#U', false);

    // `code`
    if ($this->isAllowed('`'))
      $this->registerLinePattern('processCode',    '#\`(\S[^'.TEXY_HASH.']*)MODIFIER?(?<!\ )\`()#U');

    // `=samp
    $this->registerBlockPattern('processBlock',    '#^`=(none|code|kbd|samp|var|span)$#mUi');
  }




  /***
   * Callback function: **.... .(title)[class]{style}**
   * @return string
   */
  function processPhrase(&$lineParser, &$matches, $tags)
  {
    list($match, $mContent, $mMod1, $mMod2, $mMod3, $mAdditional) = $matches;
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}

    $tags = array_reverse(explode(' ', $tags));
    $el = null;

    foreach ($tags as $tag) {
      $el = &new TexyInlineTagElement($this->texy);
      $el->tag = $tag;
      if ($tag == 'acronym') $el->modifier->title = $mAdditional;


      $mContent = $el->addTo($lineParser->element, $mContent);
    }
    if ($el)
      $el->modifier->setProperties($mMod1, $mMod2, $mMod3);

    return $mContent;
  }





  /***
   * Callback function `=code
   */
  function processBlock(&$blockParser, &$matches)
  {
    list($match, $mTag) = $matches;
    //    [1] => ...

    $this->tag = strtolower($mTag);
    if ($this->tag == 'none') $this->tag = '';
  }






  /***
   * Callback function: `.... .(title)[class]{style}`
   * @return string
   */
  function processCode(&$lineParser, &$matches)
  {
    list($match, $mContent, $mMod1, $mMod2, $mMod3) = $matches;
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}

    $texy = &$this->texy;
    $el = &new TexyTextualElement($texy);
    $el->contentType = TEXY_CONTENT_TEXTUAL;
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3);
    $el->setContent($mContent, false);  // content isn't html safe
    $el->tag = $this->allowed['`'];

    if ($this->codeUserFunction)
      call_user_func_array($this->codeUserFunction, array(&$el));

    $el->safeContent(); // ensure that content is HTML safe

    if (isset($texy->modules['TexyLongWordsModule']))
      $texy->modules['TexyLongWordsModule']->linePostProcess($el->content);

    return $el->addTo($lineParser->element);
  }








  /***
   * User callback - PROTECT PHRASE
   * @return string
   */
  function processProtect(&$lineParser, &$matches, $isHtmlSafe = false)
  {
    list($match, $mContent) = $matches;

    $el = &new TexyTextualElement($this->texy);
    $el->contentType = TEXY_CONTENT_TEXTUAL;
    $el->setContent( Texy::freezeSpaces($mContent), $isHtmlSafe );

    return $el->addTo($lineParser->element);
  }




} // TexyPhrasesModule











/**
 * AUTOMATIC REPLACEMENTS MODULE CLASS
 */
class TexyQuickCorrectModule extends TexyModule {
  // options
  var $doubleQuotes = array('&bdquo;', '&ldquo;');
  var $singleQuotes = array('&sbquo;', '&lsquo;');
  var $dash         = '&ndash;';




  function linePostProcess(&$text)
  {
    if (!$this->allowed) return;

    $HASHS   = '(['.TEXY_HASH_SOFT.']*)';
    $CHAR    = TEXY_CHAR;
    $UTF     = TEXY_PATTERN_UTF;
    $DASH    = $this->dash;

    $replace = array(
         '#(?<!&quot;|\w)&quot;(?!\ |&quot;)(.+)(?<!\ |&quot;)&quot;(?!&quot;)()#U'
                              => $this->doubleQuotes[0].'$1'.$this->doubleQuotes[1],          // double ""
         "#(?<!&\#039;|\w)&\#039;(?!\ |&\#039;)(.+)(?<!\ |&\#039;)&\#039;(?!&\#039;)()#U$UTF"
                              => $this->singleQuotes[0].'$1'.$this->singleQuotes[1],          // single ''
         '#(\S|^) ?\.{3}#m'                                   => '$1&#8230;',                 // ellipsis  ...
         '#(\d| )-(\d| )#'                                    => "\$1$DASH\$2",               // en dash    -
         '#,-#'                                               => ",$DASH",                    // en dash    ,-
         '#(\d{1,2}\.) (\d{1,2}\.) (\d\d)#'                   => '$1&nbsp;$2&nbsp;$3',        // date 23. 1. 1998
         '#(\d{1,2}\.) (\d{1,2}\.)#'                          => '$1&nbsp;$2',                // date 23. 1.
         '# -- #'                                             => " $DASH ",                   // en dash    --
         '# -&gt; #'                                          => ' &#8594; ',                 // right arrow ->
         '# &lt;- #'                                          => ' &#8592; ',                 // left arrow ->
         '# &lt;-&gt; #'                                      => ' &#8596; ',                 // left right arrow <->
         '#(\d+) ?x ?(\d+)#'                                  => '$1&#215;$2',                // dimension sign x
         '#(\S ?)\(TM\)#i'                                    => '$1&trade;',                 // trademark      (TM)
         '#(\S ?)\(R\)#i'                                     => '$1&reg;',                   // registered     (R)
         '#(\S ?)\(C\)#i'                                     => '$1&copy;',                  // copyright      (C)
         '#(\d{1,3}) (\d{3}) (\d{3}) (\d{3})#'                => '$1&nbsp;$2&nbsp;$3&nbsp;$4',// (phone) number 1 123 123 123
         '#(\d{1,3}) (\d{3}) (\d{3})#'                        => '$1&nbsp;$2&nbsp;$3',        // (phone) number 1 123 123
         '#(\d{1,3}) (\d{3})#'                                => '$1&nbsp;$2',                // number 1 123
         "#(?<=^| )(\d+)$HASHS $HASHS([$CHAR])#m$UTF"         => '$1$2&nbsp;$3$4',            // space after number
         "#(?<=^|[^0-9$CHAR])$HASHS([ksvzouiKSVZOUIA])$HASHS $HASHS([0-9$CHAR])#m$UTF"  => '$1$2$3&nbsp;$4$5',  // space after preposition
    );

    $text = preg_replace(array_keys($replace), array_values($replace), $text);
  }



} // TexyQuickCorrectModule














/**
 * QUOTE & BLOCKQUOTE MODULE CLASS
 */
class TexyQuoteModule extends TexyModule {
  var $allowed = array(
         'line'  => true,
         'block' => true
         );


  /***
   * Module initialization.
   */
  function init()
  {
    if ($this->isAllowed('block'))
      $this->registerBlockPattern('processBlock', '#^(?:MODIFIER_H\n)?>(\ +|:)(\S.*)$#mU');

    if ($this->isAllowed('line'))
      $this->registerLinePattern('processLine', '#(?<!\>)(\>\>)(?!\ |\>)(.+)MODIFIER?(?<!\ |\<)\<\<(?!\<)'.TEXY_PATTERN_LINK.'?()#U', 'q');
  }


  /***
   * Callback function: >>.... .(title)[class]{style}<<:LINK
   * @return string
   */
  function processLine(&$lineParser, &$matches, $tag)
  {
    list($match, $mMark, $mContent, $mMod1, $mMod2, $mMod3, $mLink) = $matches;
    //    [1] => **
    //    [2] => ...
    //    [3] => (title)
    //    [4] => [class]
    //    [5] => {style}
    //    [6] => LINK

    $texy = & $this->texy;
    $el = &new TexyQuoteElement($texy);
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3);

    if ($mLink)
      $el->cite->set($mLink);

    return $el->addTo($lineParser->element, $mContent);
  }




  /***
   * Callback function (for blocks)
   *
   *            > They went in single file, running like hounds on a strong scent,
   *            and an eager light was in their eyes. Nearly due west the broad
   *            swath of the marching Orcs tramped its ugly slot; the sweet grass
   *            of Rohan had been bruised and blackened as they passed.
   *            >:http://www.mycom.com/tolkien/twotowers.html
   *
   */
  function processBlock(&$blockParser, &$matches)
  {
    list($match, $mMod1, $mMod2, $mMod3, $mMod4, $mSpaces, $mContent) = $matches;
    //    [1] => (title)
    //    [2] => [class]
    //    [3] => {style}
    //    [4] => <>
    //    [5] => spaces |
    //    [6] => ... / LINK

    $texy = & $this->texy;
    $el = &new TexyBlockQuoteElement($texy);
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

    $content = '';
    $linkTarget = '';
    $spaces = '';
    do {
      if ($mSpaces == ':') $linkTarget = $mContent;
      else {
        if ($spaces === '') $spaces = strlen($mSpaces);
        $content .= $mContent . TEXY_NEWLINE;
      }

      if (!$blockParser->match("#^>(?:|(\ {1,$spaces}|:)(.*))()$#mA", $matches)) break;
      list($match, $mSpaces, $mContent) = $matches;
    } while (true);

    if ($linkTarget)
      $el->cite->set($linkTarget);

    $el->parse($content);

    $blockParser->addChildren($el);
  }



} // TexyQuoteModule





/****************************************************************************
                               TEXY! DOM ELEMENTS                          */




/**
 * HTML ELEMENT BLOCKQUOTE
 */
class TexyBlockQuoteElement extends TexyBlockElement {
  var $tag = 'blockquote';
  var $cite;


  function TexyBlockQuoteElement(&$texy)
  {
    parent::TexyBlockElement($texy);
    $this->cite = & $texy->createURL();
  }


  function generateTag(&$tag, &$attr)
  {
    parent::generateTag($tag, $attr);
    if ($this->cite->URL) $attr['cite'] = $this->cite->URL;
  }

} // TexyBlockQuoteElement





/**
 * HTML TAG QUOTE
 */
class TexyQuoteElement extends TexyInlineTagElement {
  var $tag = 'q';
  var $cite;


  function TexyQuoteElement(&$texy)
  {
    parent::TexyInlineTagElement($texy);
    $this->cite = & $texy->createURL();
  }


  function generateTag(&$tag, &$attr)
  {
    parent::generateTag($tag, $attr);
    if ($this->cite->URL) $attr['cite'] = $this->cite->URL;
  }


} // TexyBlockQuoteElement














/**
 * SCRIPTS MODULE CLASS
 */
class TexyScriptModule extends TexyModule {


  /***
   * Module initialization.
   */
  function init()
  {
    if ($this->allowed)
      $this->registerLinePattern('processLine', '#\$\{([^\}'.TEXY_HASH.'])+\}()#U');
  }



  /***
   * Callback function: ${...}
   * @return string
   */
  function processLine(&$lineParser, &$matches, $tag)
  {
    list($match, $mContent) = $matches;
    //    [1] => ...

    $el = &new TexyScriptElement($this->texy);
    return $el->addTo($lineParser->element);
  }



} // TexyScriptModule







/****************************************************************************
                               TEXY! DOM ELEMENTS                          */




/**
 * Texy! ELEMENT SCRIPTS + VARIABLES
 */
class TexyScriptElement extends TexyTextualElement {

  function generateContent()
  {
    return '<--internal use-->';
  }

}  // TexyScriptElement













/**
 * TABLE MODULE CLASS
 */
class TexyTableModule extends TexyModule {


  /***
   * Module initialization.
   */
  function init()
  {
    if ($this->allowed)
      $this->registerBlockPattern('processBlock', '#^(?:MODIFIER_HV\n)?'      // .{color: red}
                                                . '(\|.*)$#mU');              // | ....
  }





  /***
   * Callback function (for blocks)
   *
   *            .(title)[class]{style}>
   *            |------------------
   *            | xxx | xxx | xxx | .(..){..}[..]
   *            |------------------
   *            | aa  | bb  | cc  |
   *
   */
  function processBlock(&$blockParser, &$matches)
  {
    list($match, $mMod1, $mMod2, $mMod3, $mMod4, $mMod5, $mRow) = $matches;
    //    [1] => (title)
    //    [2] => [class]
    //    [3] => {style}
    //    [4] => >
    //    [5] => _
    //    [6] => | ....

    $texy = & $this->texy;
    $el = &new TexyTableElement($texy);
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4, $mMod5);
    $el->colsCount = 0;
    $blockParser->addChildren($el);

    $head = false;
    $colModifier = array();
    $elField = null;
    $elRow = null;


    if (preg_match('#^\|(\#|\=){2,}(?!\\1)(.*)\\1*\|? *'.TEXY_PATTERN_MODIFIER_H.'?()$#U', $mRow, $matches)) {
      list($match, $mChar, $mContent, $mModCap1, $mModCap2, $mModCap3, $mModCap4) = $matches;
      $elCaption = &new TexyTextualElement($texy);
      $elCaption->tag = 'caption';
      $elCaption->parse($mContent);
      $elCaption->modifier->setProperties($mModCap1, $mModCap2, $mModCap3, $mModCap4);
      $el->children[] = & $elCaption;

      if (!$blockParser->match('#^(\|.*)$#mU', $matches)) return;
      $mRow = $matches[0];
    }


    preg_match('#^\|(.+)(?:|\|\ *'.TEXY_PATTERN_MODIFIER_HV.'?)()$#U', $mRow, $matches);
    do {
      list($match, $mContent, $mModRow1, $mModRow2, $mModRow3, $mModRow4, $mModRow5) = $matches;
      //    [1] => ....
      //    [2] => (title)
      //    [3] => [class]
      //    [4] => {style}
      //    [5] => >
      //    [6] => _

      if (preg_match('#\|\-{3,}$#AU', $match)) {
        $head = !$head;
        continue;
      }

      $elLastRow = & $elRow->children;
      $elRow = &new TexyTableRowElement($texy);
      $elRow->modifier->setProperties($mModRow1, $mModRow2, $mModRow3, $mModRow4, $mModRow5);
      $elRow->isHead = $head;
      $el->children[] = & $elRow;

      $cols = explode('|', $mContent);
      $col = 0;
      foreach ($cols as $key => $s) {
        $col++;

        if (($s == '') && $elField) { // colspan
          $elField->colSpan++;
          continue;
        }

        if (!preg_match('#(?-U)(\*|\^)?\ *'.TEXY_PATTERN_MODIFIER_HV.'?(?U)(.*)'.TEXY_PATTERN_MODIFIER_HV.'?\ *()$#AU', $s, $matchesC)) break;
        list($match, $mHead, $mModCol1, $mModCol2, $mModCol3, $mModCol4, $mModCol5, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mMod5) = $matchesC;
        //    [1] => * ^
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => <
        //    [6] => ^
        //    [7] => ....
        //    [8] => (title)
        //    [9] => [class]
        //    [10] => {style}
        //    [11] => <>
        //    [12] => ^

        if (($mHead == '^') && $elLastRow) {  // rowspan
          if (isset($elLastRow[$col]))  $elLastRow[$col]->rowSpan++;
          continue;
        }

        if (!isset($colModifier[$col])) { $colModifier[$col] = &$texy->createModifier(); }
        if ($mModCol1 || $mModCol2 || $mModCol3 || $mModCol4 || $mModCol5) {
          $colModifier[$col]->clear();
          $colModifier[$col]->setProperties($mModCol1, $mModCol2, $mModCol3, $mModCol4, $mModCol5);
        }

        $elField = &new TexyTableFieldElement($texy);
        $elField->isHead = ($head || ($mHead == '*'));
        $elField->modifier->copyFrom($colModifier[$col]);
        $elField->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4, $mMod5);
        $elField->parse($mContent);
        $elRow->children[$col] = & $elField;
      } // foreach

      $el->colsCount = max($el->colsCount, $col);

    } while ($blockParser->match('#^\|(.+)(?:|\|\ *'.TEXY_PATTERN_MODIFIER_HV.'?)()$#mUA', $matches));

  }



} // TexyTableModule






/****************************************************************************
                               TEXY! DOM ELEMENTS                          */





/**
 * HTML ELEMENT TABLE
 */
class TexyTableElement extends TexyBlockElement {
  var $tag = 'table';

} // TexyTableElement






/**
 * HTML ELEMENT TR
 */
class TexyTableRowElement extends TexyBlockElement {
  var $tag = 'tr';
  var $isHead;  // not used yet

} // TexyTableRowElement






/**
 * HTML ELEMENT TD / TH
 */
class TexyTableFieldElement extends TexyTextualElement {
  var $colSpan = 1;
  var $rowSpan = 1;
  var $isHead;

  function generateTag(&$tag, &$attr)
  {
    parent::generateTag($tag, $attr);
    $tag = $this->isHead ? 'th' : 'td';
    if ($this->colSpan <> 1) $attr['colspan'] = (int) $this->colSpan;
    if ($this->rowSpan <> 1) $attr['rowspan'] = (int) $this->rowSpan;
  }

} // TexyTableFieldElement















/**
 * AUTOMATIC REPLACEMENTS MODULE CLASS
 */
class TexySmiliesModule extends TexyModule {
  var $allowed   = false;
  var $icons     = array (
            ':-)'  =>  'smile.gif',
            ':-('  =>  'sad.gif',
            ';-)'  =>  'wink.gif',
            ':-D'  =>  'biggrin.gif',
            '8-O'  =>  'eek.gif',
            '8-)'  =>  'cool.gif',
            ':-?'  =>  'confused.gif',
            ':-x'  =>  'mad.gif',
            ':-P'  =>  'razz.gif',
            ':-|'  =>  'neutral.gif',
      );
  var $root      = 'images/smilies/';
  var $class     = '';



  /***
   * Module initialization.
   */
  function init()
  {
    Texy::adjustDir($this->root);

    if ($this->allowed) {
      krsort($this->icons);
      $re = array();
      foreach ($this->icons as $key => $value)
        $re[] = preg_quote($key) . '+';

      $crazyRE = '#(?<=^|[\\x00-\\x20])(' . implode('|', $re) . ')#';

      $this->registerLinePattern('processLine', $crazyRE);
    }
  }






  /***
   * Callback function: :-)
   * @return string
   */
  function processLine(&$lineParser, &$matches)
  {
    $match = &$matches[0];
    //    [1] => **
    //    [2] => ...
    //    [3] => (title)
    //    [4] => [class]
    //    [5] => {style}
    //    [6] => LINK

    $texy = & $this->texy;
    $el = &new TexyImageElement($texy);
    $el->modifier->title = $match;
    $el->modifier->classes[] = $this->class;
    $el->image->root = $this->root;

     // find the closest match
    foreach ($this->icons as $key => $value)
      if (substr($match, 0, strlen($key)) == $key) {
        $el->image->set($value);
        break;
      }

    return $el->addTo($lineParser->element);
  }



} // TexySmiliesModule









/**
 * Texy! MAIN CLASS
 * ----------------
 *
 * usage:
 *     $texy = new Texy();
 *     $html = $texy->process($text);
 */

class Texy {
  // modules
  var $modules;
  // shortcuts to modules
  var $images;       // shortcut to $modules[....]
  var $links;        // shortcut to $modules[....]
  var $headings;     // shortcut to $modules[....]

  // parsed text - DOM structure
  var $DOM;
  var $summary;
  var $styleSheet;

  // options
  var $tabWidth     = 8;      // all tabs are converted to spaces
  var $allowedClasses = true;   // true or list of allowed classes
  var $allowedStyles  = true;   // true or list of allowed styles
  var $obfuscateEmail = true;


  // private
  var $patternsLine = array();
  var $patternsBlock = array();
  var $genericBlock;
  var $blockElements  = array('address', 'blockquote', 'caption', 'col', 'colgroup', 'dd', 'div', 'dl', 'dt', 'fieldset', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'iframe', 'legend', 'li', 'object', 'ol', 'p', 'param', 'pre', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul');
  var $inlineElements = array('a', 'abbr', 'acronym', 'area', 'b', 'big', 'br', 'button', 'cite', 'code', 'del', 'dfn', 'em', 'i', 'img', 'input', 'ins', 'kbd', 'label', 'map', 'noscript', 'optgroup', 'option', 'q', 'samp', 'script', 'select', 'small', 'span', 'strong', 'sub', 'sup', 'textarea', 'tt', 'var');
  var $emptyElements  = array('img', 'hr', 'br', 'input', 'meta', 'area', 'base', 'col', 'link', 'param');
  var $validElements;






  /***
   * @constructor
   ***/
  function Texy()
  {
    // init some other variables
    $this->summary->images = array();
    $this->summary->links = array();
    $this->summary->preload = array();
    $this->styleSheet = '';
    // little trick - isset($array[$item]) is much faster than in_array($item, $array)
    $this->blockElements = array_flip($this->blockElements);
    $this->inlineElements = array_flip($this->inlineElements);
    $this->emptyElements = array_flip($this->emptyElements);
    $this->validElements = array_merge($this->inlineElements, $this->blockElements);

    // load all modules
    $this->loadModules();

    // this is shortcuts for easier configuration ( use e.g. $texy->image->root instead of $texy->modules['TexyImageModule']->root
    $this->images   = & $this->modules['TexyImageModule'];
    $this->links    = & $this->modules['TexyLinkModule'];
    $this->headings = & $this->modules['TexyHeadingModule'];

    // example of link reference ;-)
    $elRef = &new TexyLinkReference($this, 'http://www.texy.info/', 'Texy!');
    $elRef->modifier->title = 'Text to HTML converter and formatter';
    $this->links->addReference('texy', $elRef);
  }






  /***
   * Create array of all used modules ($this->modules)
   * This array can be changed by overriding this method (by subclasses)
   * or directly in main code
   ***/
  function loadModules()
  {
    // Line parsing - order is not much important
    $this->registerModule('TexyScriptModule');
    $this->registerModule('TexyHTMLTagModule');
    $this->registerModule('TexyImageModule');
    $this->registerModule('TexyLinkModule');
    $this->registerModule('TexyPhrasesModule');
    $this->registerModule('TexySmiliesModule');
    $this->registerModule('TexyControlModule');

    // block parsing - order is not much important
    $this->registerModule('TexyBlockModule');
    $this->registerModule('TexyHeadingModule');
    $this->registerModule('TexyHorizlineModule');
    $this->registerModule('TexyQuoteModule');
    $this->registerModule('TexyListModule');
    $this->registerModule('TexyDefinitionListModule');
    $this->registerModule('TexyTableModule');
    $this->registerModule('TexyImageDescModule');
    $this->registerModule('TexyGenericBlockModule');

    // post process
    $this->registerModule('TexyQuickCorrectModule');
    $this->registerModule('TexyLongWordsModule');
    $this->registerModule('TexyFormatterModule');  // should be last post-processing module!
  }



  function registerModule($className)
  {
    $this->modules[$className] = &new $className($this);
  }



  /***
   * Initialization
   * It is called between constructor and first use (method parse)
   ***/
  function init()
  {
    if (!$this->modules) die('Texy: No modules installed');

    foreach (array_keys($this->modules) as $name) {
      $module = & $this->modules[$name];
      if (is_a($module, 'TexyModule'))
        $module->init();    // init modules
      else
        unset($this->modules[$name]);
    }
  }





  /***
   * Convert Texy! document in (X)HTML code
   * This is shortcut for parse() & DOM->toHTML()
   * @return string
   ***/
  function process($text, $singleLine = false)
  {
    if ($singleLine)
      $this->parseLine($text);
    else
      $this->parse($text);

    return $this->DOM->toHTML();
 }






  /***
   * Convert Texy! document into internal DOM structure ($this->DOM)
   * Before converting it normalize text and call all pre-processing modules
   ***/
  function parse($text)
  {
      // initialization
    $this->init();

      ///////////   PROCESS
    $this->DOM = &new TexyDOM($this);
    $this->DOM->parse($text);
  }





  /***
   * Convert Texy! single line text into internal DOM structure ($this->DOM)
   ***/
  function parseLine($text)
  {
      // initialization
    $this->init();

      ///////////   PROCESS
    $this->DOM = &new TexyDOMLine($this);
    $this->DOM->parse($text);
  }




  /***
   * Convert internal DOM structure ($this->DOM) to (X)HTML code
   * and call all post-processing modules
   * @return string
   ***/
  function toHTML()
  {
    return $this->DOM->toHTML();
  }



  /***
   * Switch Texy and default modules to safe mode
   * Suitable for 'comments' and other usages, where input text may insert attacker
   ***/
  function safeMode()
  {
    $this->allowedClasses = false;                                     // no class or ID are allowed
    $this->allowedStyles  = false;                                     // style modifiers are disabled
    $this->modules['TexyHTMLTagModule']->safeMode();                   // only HTML tags and attributes specified in $safeTags array are allowed
    $this->modules['TexyBlockModule']->safeMode();                     // make /--html blocks HTML safe
    $this->images->allowed = false;                                    // disable images
    $this->links->forceNoFollow = true;                                // force rel="nofollow"
  }




  /***
   * Switch Texy and default modules to (default) trust mode
   ***/
  function trustMode()
  {
    $this->allowedClasses = true;                                          // classes and id are allowed
    $this->allowedStyles  = true;                                          // inline styles are allowed
    $this->modules['TexyHTMLTagModule']->trustMode();                      // full support for HTML tags
    $this->modules['TexyBlockModule']->trustMode();                        // no-texy blocks are free of use
    $this->images->allowed = true;                                         // enable images
    $this->links->forceNoFollow = false;                                   // disable automatic rel="nofollow"
  }







  /***
   * Like htmlSpecialChars, but preserve entities
   * @return string
   * @static
   ***/
  function htmlChars($s, $preserveEntities = true)
  {
    if ($preserveEntities)
      return preg_replace('#'.TEXY_PATTERN_ENTITY.'#i', '&$1;', htmlSpecialChars($s, ENT_QUOTES));
    else
     htmlSpecialChars($s, ENT_QUOTES);
  }





  /***
   * Create a URL class and return it. Subclasses can override
   * this method to return an instance of inherited URL class.
   * @return TexyURL
   ***/
  function &createURL()
  {
    return new TexyURL($this);
  }



  /***
   * Create a modifier class and return it. Subclasses can override
   * this method to return an instance of inherited modifier class.
   * @return TexyModifier
   ***/
  function &createModifier()
  {
    return new TexyModifier($this);
  }




  /***
   * Build string which represents (X)HTML opening tag
   * @param string   tag
   * @param array    associative array of attributes and values
   * @return string
   * @static
   ***/
  function openingTag($tag, $attr = null, $empty = false)
  {
    if (!$tag) return '';

    $empty = TEXY_XHTML && ($empty || (Texy::classifyElement($tag) & TEXY_ELEMENT_EMPTY));

    $attrStr = '';
    if ($attr) {
      $attr = array_change_key_case($attr, CASE_LOWER);
      foreach ($attr as $name => $value) {
        $value = trim($value);
        if ($value == '') continue;
        $attrStr .= ' '
                    . Texy::htmlChars($name)
                    . '="'
                    . Texy::freezeSpaces(Texy::htmlChars($value))   // freezed spaces will be preserved during reformating
                    . '"';
      }
    }

    return '<' . $tag . $attrStr . ($empty ? ' /' : '') . '>';
  }



  /***
   * Build string which represents (X)HTML opening tag
   * @return string
   * @static
   ***/
  function closingTag($tag)
  {
    if (!$tag) return '';

    return (Texy::classifyElement($tag) & TEXY_ELEMENT_EMPTY) ? '' : '</'.$tag.'>';
  }



  /***
   * HTML tag classification
   * @return bool
   * @static
   ***/
  function classifyElement($tag)
  {
    static $blockElements;
    static $inlineElements;
    static $emptyElements;
    if (!$blockElements) {
      $vars = get_class_vars('Texy');
      $blockElements = array_flip($vars['blockElements']);
      $inlineElements = array_flip($vars['inlineElements']);
      $emptyElements = array_flip($vars['emptyElements']);
    }

    return (isset($emptyElements[$tag]) ? TEXY_ELEMENT_EMPTY : 0)
         | (isset($blockElements[$tag]) ? TEXY_ELEMENT_BLOCK :
           (isset($inlineElements[$tag]) ? TEXY_ELEMENT_INLINE : 0));
  }



  /***
   * Add right slash
   * @static
   ***/
  function adjustDir(&$name)
  {
    if ($name) $name = rtrim($name, '/\\') . '/';
  }



  /***
   * Translate all white spaces (\t \n \r space) to meta-spaces \x16-\x19
   * which are ignored by some formatting functions
   * @return string
   * @static
   ***/
  function freezeSpaces($s)
  {
    return strtr($s, " \t\r\n", "\x16\x17\x18\x19");
  }


  /***
   * Revert meta-spaces back to normal spaces
   * @return string
   * @static
   ***/
  function unfreezeSpaces($s)
  {
    return strtr($s, "\x16\x17\x18\x19", " \t\r\n");
  }



  /***
   * Generate unique HASH key - useful for freezing (folding) some substrings
   * Key consist of unique chars \x1A, \x1C-\x1F (soft)
   *                             \x1B, \x1C-\x1F (hard)
   * Special mark:               \x15 (not used)
   * @return string
   * @static
   ***/
  function hashKey($strength = null)
  {
    static $counter;
    $border = ($strength == TEXY_SOFT) ? "\x1A" : "\x1B";
    return $border . strtr(base_convert(++$counter, 10, 4), '0123', "\x1C\x1D\x1E\x1F") . $border;
  }





} // Texy















/**
 * INTERNAL PARSING BLOCK STRUCTURE
 * --------------------------------
 */
class TexyBlockParser {
  var $element;   // TexyBlockElement
  var $text;      // text splited in array of lines
  var $offset;


  // constructor
  function TexyBlockParser(& $element)
  {
    $this->element = &$element;
  }


  // match current line against RE.
  // if succesfull, increments current position and returns true
  function match($pattern, &$matches)
  {
    $ok = preg_match(
                $pattern . 'A', // anchored
                $this->text,
                $matches,
                PREG_OFFSET_CAPTURE,
                $this->offset);
    if ($ok) {
      $this->offset += strlen($matches[0][0]) + 1; // 1 = "\x"
      foreach ($matches as $key => $value) $matches[$key] = $value[0];
    }
    return $ok;
  }


  function addChildren(&$el)
  {
    $this->element->children[] = &$el;
  }


  function parse($text)
  {
      ///////////   INITIALIZATION
    $texy = &$this->element->texy;
    $this->text = & $text;
    $this->offset = 0;
    $this->element->children = array();

    $keys = array_keys($texy->patternsBlock);
    $arrMatches = $arrPos = array();
    foreach ($keys as $key) $arrPos[$key] = -1;

      ///////////   PARSING
    do {
      $minKey = -1;
      $minPos = strlen($this->text);
      if ($this->offset >= $minPos) break;

      foreach ($keys as $index => $key) {
        if ($arrPos[$key] === false) continue;

        if ($arrPos[$key] < $this->offset) {
          $delta = ($arrPos[$key] == -2) ? 1 : 0;
          $matches = & $arrMatches[$key];
          if (preg_match(
                    $texy->patternsBlock[$key]['pattern'],
                    $text,
                    $matches,
                    PREG_OFFSET_CAPTURE,
                    $this->offset + $delta)) {

            $arrPos[$key] = $matches[0][1];
            foreach ($matches as $keyX => $valueX) $matches[$keyX] = $valueX[0];

          } else {
            unset($keys[$index]);
            continue;
          }
        }

        if ($arrPos[$key] === $this->offset) { $minKey = $key; break; }

        if ($arrPos[$key] < $minPos) { $minPos = $arrPos[$key]; $minKey = $key; }
      } // foreach

      $next = ($minKey == -1) ? strlen($text) : $arrPos[$minKey];

      if ($next > $this->offset) {
        $str = substr($text, $this->offset, $next - $this->offset);
        $this->offset = $next;
        call_user_func_array($texy->genericBlock, array(&$this, $str));
        continue;
      }

      $px = & $texy->patternsBlock[$minKey];
      $matches = & $arrMatches[$minKey];
      $this->offset = $arrPos[$minKey] + strlen($matches[0]) + 1;   // 1 = \n
      $ok = call_user_func_array($px['func'], array(&$this, $matches, $px['user']));
      if ($ok === false) { // module rejects text
        $this->offset = $arrPos[$minKey]; // turn offset back
        $arrPos[$minKey] = -2;
        continue;
      }

      $arrPos[$minKey] = -1;

    } while (1);
  }

} // TexyBlockParser








/**
 * INTERNAL PARSING LINE STRUCTURE
 * -------------------------------
 */
class TexyLineParser {
  var $element;   // TexyTextualElement


  // constructor
  function TexyLineParser(& $element)
  {
    $this->element = &$element;
  }



  function parse($text)
  {
      ///////////   INITIALIZATION
    $element = &$this->element;
    $element->content = & $text;
    $element->htmlSafe = true;
    $texy = &$element->texy;

    $offset = 0;
    $hashStrLen = 0;
    $keys = array_keys($texy->patternsLine);
    $arrMatches = $arrPos = array();
    foreach ($keys as $key) $arrPos[$key] = -1;


      ///////////   PARSING
    do {
      $minKey = -1;
      $minPos = strlen($text);

      foreach ($keys as $index => $key) {
        if ($arrPos[$key] < $offset) {
          $delta = ($arrPos[$key] == -2) ? 1 : 0;
          $matches = & $arrMatches[$key];
          if (preg_match($texy->patternsLine[$key]['pattern'],
                   $text,
                   $matches,
                   PREG_OFFSET_CAPTURE,
                   $offset+$delta)) {
            if (!strlen($matches[0][0])) continue;
            $arrPos[$key] = $matches[0][1];
            foreach ($matches as $keyx => $value) $matches[$keyx] = $value[0];

          } else {

            unset($keys[$index]);
            continue;
          }
        } // if

        if ($arrPos[$key] == $offset) { $minKey = $key; break; }

        if ($arrPos[$key] < $minPos) { $minPos = $arrPos[$key]; $minKey = $key; }

      } // foreach

      if ($minKey == -1) break;

      $px = & $texy->patternsLine[$minKey];
      $offset = $arrPos[$minKey];
      $replacement = call_user_func_array($px['replacement'], array(&$this, $arrMatches[$minKey], $px['user']));
      $len = strlen($arrMatches[$minKey][0]);
      $text = substr_replace(
                        $text,
                        $replacement,
                        $offset,
                        $len);

      $delta = strlen($replacement) - $len;
      foreach ($keys as $key) {
        if ($arrPos[$key] < $offset + $len) $arrPos[$key] = -1;
        else $arrPos[$key] += $delta;
      }

      $arrPos[$minKey] = -2;

    } while (1);

    $text = Texy::htmlChars($text);

    if ($element->contentType == TEXY_CONTENT_NONE) {
      $s = trim($element->toText());
      if (strlen($s)) $element->contentType = TEXY_CONTENT_TEXTUAL;
    }

    $modules = &$texy->modules;
    foreach (array_keys($modules) as $name)
      $modules[$name]->linePostProcess($text);
  }

} // TexyLineParser











// PHP 4 & 5 compatible object cloning:  $a = clone ($b);
if (((int) phpversion() < 5) && (!function_exists('clone')))
 eval('function &clone($obj) { return $obj; }');







?>