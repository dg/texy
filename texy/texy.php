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


define('TEXY', 'Version 1rc (c) David Grudl, http://www.dgx.cz');


require_once('texy-constants.php');      // regular expressions & other constants
require_once('texy-modifier.php');       // modifier processor
require_once('texy-url.php');            // object encapsulate of URL
require_once('texy-dom.php');        // Texy! DOM element's base class
require_once('texy-module.php');         // Texy! module base class
require_once('modules/tm-code.php');
require_once('modules/tm-block.php');
require_once('modules/tm-definition-list.php');
require_once('modules/tm-formatter.php');
require_once('modules/tm-generic-block.php');
require_once('modules/tm-heading.php');
require_once('modules/tm-horiz-line.php');
require_once('modules/tm-html-tag.php');
require_once('modules/tm-image.php');
require_once('modules/tm-image-description.php');
require_once('modules/tm-link.php');
require_once('modules/tm-list.php');
require_once('modules/tm-long-words.php');
require_once('modules/tm-phrases.php');
require_once('modules/tm-quick-correct.php');
require_once('modules/tm-quote.php');
require_once('modules/tm-script.php');
require_once('modules/tm-table.php');
require_once('modules/tm-smilies.php');


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
  var $inited = false;
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
    $this->registerModule('TexyCodeModule');

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

    $this->inited = true;
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
    if (!$this->inited) $this->init();

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
    if (!$this->inited) $this->init();

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