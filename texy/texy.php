<?php

/**
 * --------------------------------------------
 *   TEXY!  * universal text->web converter *
 * --------------------------------------------
 *
 * Version 0.9 beta
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


define('TEXY', 'Version 0.9 (c) David Grudl, http://www.dgx.cz');


require_once('texy-constants.php');      // regular expressions & other constants
require_once('texy-modifier.php');       // modifier processor
require_once('texy-url.php');            // object encapsulate of URL
require_once('texy-element.php');        // Texy! DOM element's base class
require_once('texy-module.php');         // Texy! module base class
require_once('modules/tm_code.php');
require_once('modules/tm_block.php');
require_once('modules/tm_definition-list.php');
require_once('modules/tm_formatter.php');
require_once('modules/tm_generic-block.php');
require_once('modules/tm_heading.php');
require_once('modules/tm_horiz-line.php');
require_once('modules/tm_html-tag.php');
require_once('modules/tm_image.php');
require_once('modules/tm_image-description.php');
require_once('modules/tm_link.php');
require_once('modules/tm_list.php');
require_once('modules/tm_long-words.php');
require_once('modules/tm_phrases.php');
require_once('modules/tm_quick-correct.php');
require_once('modules/tm_quote.php');
require_once('modules/tm_script.php');
require_once('modules/tm_table.php');
require_once('modules/tm_smileys.php');


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

  // options
  var $tabWidth     = 8;      // all tabs are converted to spaces
  var $allowClasses = true;   // true or list of allowed classes
  var $allowStyles  = true;   // true or list of allowed styles


  // private
  var $inited = false;
  var $patternsLine = array();
  var $patternsBlock = array();



  /***
   * @constructor
   ***/
  function Texy() {

    // load all modules
    $this->loadModules();

    // this is shortcuts for easier configuration ( use e.g. $texy->image->root instead of $texy->modules['TexyImageModule']->root
    $this->images   = & $this->modules['TexyImageModule'];
    $this->links    = & $this->modules['TexyLinkModule'];
    $this->headings = & $this->modules['TexyHeadingModule'];


    // init some other variables
    $this->summary->images = array();
    $this->summary->links = array();
    $this->summary->preload = array();


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
  function loadModules() {
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
    $this->registerModule('TexyBlockQuoteModule');
    $this->registerModule('TexyListModule');
    $this->registerModule('TexyDefinitionListModule');
    $this->registerModule('TexyTableModule');
    $this->registerModule('TexyImageDescModule');
    $this->registerModule('TexyGenericBlockModule');    // should be last block module

    // post process
    $this->registerModule('TexyQuickCorrectModule');
    $this->registerModule('TexyLongWordsModule');
    $this->registerModule('TexyFormatterModule');  // should be last post-processing module!
  }



  function registerModule($className) {
    $this->modules[$className] = &new $className($this);
  }



  /***
   * Initialization
   * It is called between constructor and first use (method parse)
   ***/
  function init() {
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
  function process($text, $singleLine = false) {
    $this->parse($text, $singleLine);
    return $this->DOM->toHTML();
 }






  /***
   * Convert Texy! document into internal DOM structure ($this->DOM)
   * Before converting it normalize text and call all pre-processing modules
   ***/
  function parse($text, $singleLine = false) {
      // initialization
    if (!$this->inited) $this->init();

      ///////////   PROCESS
    if ($singleLine)
      $this->DOM = &new TexyDOMLine($this);
    else
      $this->DOM = &new TexyDOM($this);

    $this->DOM->parse($text);
  }







  /***
   * Convert internal DOM structure ($this->DOM) to (X)HTML code
   * and call all post-processing modules
   * @return string
   ***/
  function toHTML() {
    return $this->DOM->toHTML();
  }









  /***
   * Like htmlSpecialChars, but preserve entities
   * @return string
   * @static
   ***/
  function htmlChars($s, $preserveEntities = true) {
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
  function &createURL() {
    return new TexyURL();
  }



  /***
   * Create a modifier class and return it. Subclasses can override
   * this method to return an instance of inherited modifier class.
   * @return TexyModifier
   ***/
  function &createModifier() {
    return new TexyModifier($this);
  }




  /***
   * Build string which represents (X)HTML opening tag
   * @param string   tag
   * @param array    associative array of attributes and values
   * @return string
   * @static
   ***/
  function openingTag($tag, $attr = null) {
    if (!$tag) return '';

    static $emptyElements;
    if (!$emptyElements) $emptyElements = array_flip(array('img', 'hr', 'br', 'input', 'meta'));

    $attrStr = '';
    if ($attr)
      foreach ($attr as $name => $value) {
        $value = trim($value);
        if ($value == '') continue;
        $attrStr .= ' '
                    . Texy::htmlChars($name)
                    . '="'
                    . Texy::freezeSpaces(Texy::htmlChars($value))   // freezed spaces will be preserved during reformating
                    . '"';
      }
    return '<' . $tag . $attrStr . (isset($emptyElements[$tag]) && TEXY_XHTML ? ' /' : '') . '>';
  }



  /***
   * Build string which represents (X)HTML opening tag
   * @return string
   * @static
   ***/
  function closingTag($tag) {
    if (!$tag) return '';

    static $emptyElements;
    if (!$emptyElements) $emptyElements = array_flip(array('img', 'hr', 'br', 'input', 'meta'));

    return isset($emptyElements[$tag]) ? '' : '</'.$tag.'>';
  }




  /***
   * Translate all white spaces (\t \n \r space) to meta-spaces \x16-\x19
   * which are ignored by some formatting functions
   * @return string
   * @static
   ***/
  function freezeSpaces($s) {
    return strtr($s, " \t\r\n", "\x16\x17\x18\x19");
  }


  /***
   * Revert meta-spaces back to normal spaces
   * @return string
   * @static
   ***/
  function unfreezeSpaces($s) {
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
  function hashKey($strength = null) {
    static $counter;
    $border = ($strength == TEXY_SOFT) ? "\x1A" : "\x1B";
    return $border . strtr(base_convert(++$counter, 10, 4), '0123', "\x1C\x1D\x1E\x1F") . $border;
  }



  /***
   * EXPERIMENTAL - DON'T USE!
   ***/
  function serialize() {
//    return serialize($this->DOM);
  }




  /***
   * EXPERIMENTAL - DON'T USE!
   ***/
  function unserialize($s) {
    $this->DOM = unserialize($s);
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
  function TexyBlockParser(& $element) {
    $this->element = &$element;
  }


  // match current line against RE.
  // if succesfull, increments current position and returns true
  function match($pattern, &$matches) {
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




  function parse($text) {
    $texy = &$this->element->texy;
    $this->text = & $text;
    $this->offset = 0;
    $children = & $this->element->children;
    $children = array();

    $arrPos = array_fill(0, count($texy->patternsBlock), -1);
    $arrMatches = array();

    do {
      $minKey = -1;
      $minPos = strlen($this->text);

      foreach (array_keys($texy->patternsBlock) as $key) {
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
            $arrPos[$key] = false;
            continue;
          }
        }

        if ($arrPos[$key] === $this->offset) { $minKey = $key; break; }

        if ($arrPos[$key] < $minPos) { $minPos = $arrPos[$key]; $minKey = $key; }
      } // foreach

      if ($minKey == -1) break;

      $px = & $texy->patternsBlock[$minKey];
      $matches = & $arrMatches[$minKey];
      $this->offset = $arrPos[$minKey] + strlen($matches[0]) + 1;   // 1 = \n
      $ret = &call_user_func_array($px['func'], array(&$this, $matches, $px['user']));
      if ($ret === false) { // module rejects text
        $this->offset = $arrPos[$minKey]; // turn offset back
        $arrPos[$minKey] = -2;
        continue;
      }

      if ($ret)
        $children[] = & $ret;

      $arrPos[$minKey] = -1;

    } while (1);
  }

} // TexyBlockParser








/**
 * INTERNAL PARSING LINE STRUCTURE
 * -------------------------------
 */
class TexyLineParser {
  var $element;   // TexyInlineElement


  // constructor
  function TexyLineParser(& $element) {
    $this->element = &$element;
  }



  function parse($text) {
    $element = &$this->element;
    $element->content = & $text;
    $texy = &$element->texy;

    $offset = 0;
    $hashStrLen = 0;
    $keys = array_keys($texy->patternsLine);
    $arrPos = array_fill(0, count($keys), -1);
    $arrMatches = array();

    do {
      $minKey = -1;
      $minPos = strlen($text);

      foreach ($keys as $i => $key) {

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

            unset($keys[$i]);
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

    if (!$element->textualContent) {
      $len = strlen($element->content);
      foreach (array_keys($element->children) as $hashKey) $len -= strlen($hashKey);
      $element->textualContent = $len > 0;
    }

    $modules = &$texy->modules;
    foreach (array_keys($modules) as $name)
      $modules[$name]->inlinePostProcess($text);
  }

} // TexyLineParser











// PHP 4 & 5 compatible object cloning:  $a = clone ($b);
if (((int) phpversion() < 5) && (!function_exists('clone')))
 eval('function &clone($obj) { return $obj; }');







?>