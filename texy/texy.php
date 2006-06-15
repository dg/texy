<?php

/**
 * --------------------------------------------
 *   TEXY!  * universal text->web converter *
 * --------------------------------------------
 *
 * Version 0.9 beta
 *
 * Copyright (c) 2003-2005, David Grudl <dave@dgx.cz>
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

  // references
  var $linkReferences  = array();  // references:   'home' => TexyLinkElement
  var $imageReferences = array();  // references:   'home' => TexyImageElement
  var $userReferences;             // user function f('home', $image): TexyLinkElement / TexyImageElement

  // parsing text
  var $DOM;
  var $elements;     // all DOM's elements in linear array
  var $freezer;

  // options
  var $UTF             = false;
  var $topHeading      = 1;             // number of top heading, 1 - 6
  var $root            = '';            // root of relative links
  var $imageRoot       = 'images/';     // root for relative images
  var $linkImageRoot   = 'images/';     // root for linked images
  var $tabWidth        = 8;
  var $imageOnClick    = 'return !popup(this.href)';  // image popup event
  var $imageLeftClass  = 'left';        // left-floated image modifier
  var $imageRightClass = 'right';       // right-floated image modifier
  var $imageAlt        = 'image';       // default image alternative text

  var $allowClasses    = true;          // true or list of allowed classes
  var $allowStyles     = true;

  // output options
  var $format;        // $format->indentSpace = "\t";    indent space
                      // $format->lineWrap    = 80;      line width, doesn't include indent space
  var $xhtml           = true;          // XHTML output, it affects empty elements: <br /> vs. <br>


  // private
  var $text;
  var $inited = false;
  var $patternsInline;
  var $patternsBlock;




  function __constructor() {
    // load all modules
    $this->loadModules();

    $this->freezer   = &new TexyFreezer();
    $this->format = &new TexyFormatter();
    $this->DOM    = &new TexyDOM($this);

    // example of link reference ;-)
    $elLink = new TexyLinkElement($this);
    $elLink->setLink('http://www.texy.info/');
    $elLink->childern = array('Texy!');
    $this->addReference('texy', $elLink);
  }




  function Texy() {  // PHP 4 compatible constructor
    $args = & func_get_args();
    call_user_func_array(array(& $this, '__constructor'), $args);
  }




    // default configuration
  function loadModules() {
    // preprocess
    $this->modules['notexy']       = &new TexyNoTexyModule($this);    

    // inline modules
    $this->modules['script']       = &new TexyScriptModule($this);
    $this->modules['htmltags']     = &new TexyHTMLTagModule($this);
    $this->modules['image']        = &new TexyImageModule($this);
    $this->modules['link']         = &new TexyLinkModule($this);
    $this->modules['phrases']      = &new TexyPhrasesModule($this);
    $this->modules['acronym']      = &new TexyAcronymModule($this);
    $this->modules['code']         = &new TexyCodeModule($this);

    // block modules
    $this->modules['heading']      = &new TexyHeadingModule($this);
    $this->modules['horizline']    = &new TexyHorizlineModule($this);
    $this->modules['blockquote']   = &new TexyBlockQuoteModule($this);
    $this->modules['list']         = &new TexyListModule($this);
    $this->modules['deflist']      = &new TexyDefinitionListModule($this);
    $this->modules['table']        = &new TexyTableModule($this);
    $this->modules['someblock']    = &new TexySomeBlockModule($this);    // should be last

    // post process
    $this->modules['correct']      = &new TexyQuickCorrectModule($this);
    $this->modules['longwords']    = &new TexyLongWordsModule($this);
  }




  // initialization (before first use)
  function init() {
    $this->patternsInline = array();
    $this->patternsBlock = array();
    
    foreach (array_keys($this->modules) as $name)
      $this->modules[$name]->init();    // init modules

    $this->inited = true;
  }





  // convert Texy! to HTML code
  function process($text) {
    $this->parse($text);
    return $this->toHTML();
      ///////////   THAT'S ALL FOLKS!
 }






  // convert Texy! to internal structures $this->DOM 
  function parse($text) {
      // initialization
    if (!$this->inited) $this->init();

      ///////////   INIT SOME VARIABLES, OBJECTS
    $this->elements = array();
    $this->text = & $text;  // for pre-processing
    $this->freezer->setText($text);

      ///////////   STANDARDIZE LINE ENDINGS TO UNIX-LIKE  (DOS, MAC)
    $text = str_replace("\r\n", TEXY_NEWLINE, $text); // DOS
    $text = str_replace("\r", TEXY_NEWLINE, $text); // Mac
    $text = preg_replace("#[\t ]+\n#", TEXY_NEWLINE, $text); // right trim

      ///////////   REPLACE TABS WITH SPACES
    while (strpos($text, "\t") !== false)
      $text = preg_replace_callback('#^(.*)\t#mU', 
                 create_function('&$matches', "return \$matches[1] . str_repeat(' ', $this->tabWidth - strlen(\$matches[1]) % $this->tabWidth);"), 
                 $text); 
                 

      ///////////   PRE-PROCESSING
    foreach (array_keys($this->modules) as $name)
      $this->modules[$name]->preProcess();

      ///////////   PROCESS
    $this->DOM->children = $this->parseBlock($text);
  }







  // convert from internal structures to HTML code
  function toHTML() {

      ///////////   INIT FREEZER
    $this->freezer->setText($this->text);

      ///////////   TO HTML STRING (WITH HASHES)
    $this->text = $this->DOM->toHTML();
    $this->text = preg_replace_callback('#<.+>#Us', array(&$this->freezer, 'add'), $this->text);

      ///////////   POST-PROCESS
    foreach (array_keys($this->modules) as $name)
      $this->modules[$name]->postProcess();

      ///////////   UNFREEZE TAGS
    $this->freezer->revert();

      ///////////   FINAL OUTPUT FORMATING
    $this->format->reformat($this->text);
    
      ///////////   UNFREEZE SPACES
    $this->freezer->unfreezeSpaces();

    return $this->text;
  }






  function &parseBlock($text) {
    $block = &new TexyBlock($this, $text);

    $keys = array_keys($this->patternsBlock);
    $arrPos = array_fill(0, count($keys), -1);
    $arrMatches = array();

    do {
      if (($block->modifierJustUpdated--) == 0) $block->modifier->clear();

      $minKey = -1;
      $minPos = strlen($block->text);

      foreach ($keys as $i => $key) {

        if ($arrPos[$key] < $block->offset) {
          $matches = & $arrMatches[$key];
          if (preg_match($this->patternsBlock[$key]['pattern'],
                   $block->text,
                   $matches,
                   PREG_OFFSET_CAPTURE,
                   $block->offset)) {
            $arrPos[$key] = $matches[0][1];
            foreach ($matches as $keyx => $value) $matches[$keyx] = $value[0];

          } else {

            unset($keys[$i]);
            continue;
          }
        } // if

        if ($arrPos[$key] == $block->offset) { $minKey = $key; break; }

        if ($arrPos[$key] < $minPos) { $minPos = $arrPos[$key]; $minKey = $key; }

      } // foreach

      if ($minKey == -1) break;

      $px = & $this->patternsBlock[$minKey];
      $matches = & $arrMatches[$minKey];
      $block->offset = $arrPos[$minKey] + strlen($matches[0]) + 1;   // 1 = \n
      call_user_func_array($px['func'], array(&$block, $matches, $px['user']));
      $arrPos[$minKey] = -1;

    } while (1);
    
    return $block->children;
  }








  function &parseInline($text) {
    $res = '';
    $offset = 0;
    $textStart = 0;
    $children = array();
    $keys = array_keys($this->patternsInline);
    $arrPos = array_fill(0, count($keys), -1);
    $arrMatches = array();

    do {
      $minKey = -1;
      $minPos = strlen($text);

      foreach ($keys as $i => $key) {

        if ($arrPos[$key] < $offset) {
          $matches = & $arrMatches[$key];
          if (preg_match($this->patternsInline[$key]['pattern'],
                   $text,
                   $matches,
                   PREG_OFFSET_CAPTURE,
                   $offset)) {
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

      $px = & $this->patternsInline[$minKey];
      if ($arrPos[$minKey] > $textStart) $children[] = substr($text, $textStart, $arrPos[$minKey] - $textStart);

      array_splice($children, count($children), 0,
        call_user_func_array($px['replacement'], array($arrMatches[$minKey], $px['user']))
      );

      $textStart = $offset = $arrPos[$minKey] + strlen($arrMatches[$minKey][0]);

      $arrPos[$minKey] = -1;

    } while (1);
    if ($textStart < strlen($text)) $children[] = substr($text, $textStart);
    return $children;
  }







  // htmlSpecialChars and preserve entities
  // static
  function htmlChars($s) {
    return preg_replace('#'.TEXY_PATTERN_ENTITY.'#i', '&$1', htmlSpecialChars($s, ENT_QUOTES));
  }





  /***
   * Add new named link / image
   ***/
  function addReference($name, &$obj, $isImage = false) {
    $name = strtolower($name);
    if ($isImage)
      $this->imageReferences[$name] = &$obj;
    else
      $this->linkReferences[$name] = &$obj;
  }




  /***
   * Receive new named link / image. If not exists, try
   * call user function to create one.
   ***/
  function &getReference($name, $isImage = false) {
    $name = strtolower($name);
    if ($isImage) {
      if (isset($this->imageReferences[$name])) return $this->imageReferences[$name];
    } else {
      if (isset($this->linkReferences[$name])) return $this->linkReferences[$name];
    }

    if ($this->userReferences) {
      return call_user_func($this->userReferences, $name, $isImage);
    }

    return false;
  }



  /***
   * Create a URL class and return it. Subclasses can override
   * this method to return an instance of inherited URL class.
   * @returns TexyURL
   ***/
  function &createURL() {
    return new TexyURL($this);
  }


  function serialize() {
    foreach (array_keys($this->elements) as $key) {
      unset($this->elements[$key]->texy);
      unset($this->elements[$key]->modifier->texy);
    }
    return serialize($this->DOM);
  }


  function unserialize($s) {
    $this->DOM = unserialize($s);
  }




} // Texy
















/**
 * INTERNAL URL STRUCTURE
 * ----------------------
 *
 * Analyse input text, detect type of url ($type)
 * and convert to final URL ($this->URL)
 *
 */
class TexyURL {
  var $URL;
  var $type;
  var $text;
  
  var $root;          // root of relative links
  var $imageRoot;     // root for relative images
  var $linkImageRoot; // root for linked images


  function TexyURL(&$texy) {
    $this->root = & $texy->root;
    $this->imageRoot = & $texy->imageRoot;
    $this->linkImageRoot = & $texy->linkImageRoot;
  }


  function set($text, $type = 0) {
    $this->text = trim($text);
    if (!$this->text) {
      $this->type = 0;
      $this->URL = '';
      return;
    }
    $this->type = $type;
    $this->analyse();
    $this->translate();
  }


  function clear() {
    $this->text = '';
    $this->type = 0;
    $this->URL = '';
  }
  
 
  function copyFrom(&$obj) {
    $this->text = $obj->text;
    $this->type = $obj->type;
    $this->URL = $obj->URL;
  }
  

  function analyse() {
    if (preg_match('#^'.TEXY_PATTERN_EMAIL.'$#i', $this->text)) $this->type |= TEXY_LINK_EMAIL;
    elseif (preg_match('#(https?://|ftp://|www\.|ftp\.|/)#Ai', $this->text)) $this->type |= TEXY_LINK_ABSOLUTE;
    else $this->type |= TEXY_LINK_RELATIVE;
  }


  function translate() {
    if ($this->type & TEXY_LINK_EMAIL) {
      $this->URL = 'mailto:'.$this->text;
      return;
    }

    if ($this->type & TEXY_LINK_ABSOLUTE) {
      if (preg_match('#www\.#Ai', $this->text)) { 
        $this->URL = 'http://'.$this->text; 
        return; 
      } elseif (preg_match('#ftp\.#Ai', $this->text)) {
        $this->URL = 'ftp://'.$this->text;
        return;
      }
      $this->URL = $this->text;
      return;
    }

    if ($this->type & TEXY_IMAGE) {   // relative inline image
      $this->URL = $this->imageRoot . $this->text;
      return;
    }  

    if ($this->type & TEXY_IMAGE_LINK) { // relative linked image
      $this->URL = $this->linkImageRoot . $this->text;
      return;
    }  

    if ($this->type & TEXY_LINK_RELATIVE) {
      $this->URL = $this->root . $this->text;
    }
  }

} // TexyURL










/**
 * INTERNAL HASHING STRUCTURE
 * --------------------------
 *
 * Useful for freezing (folding) some substrings into unique keys
 * Key consist of unique chars \x1A-\x1F 
 * Unformattable white space:  \x16-\x19
 * Special mark:               \x15 (not used)
 *
 */
class TexyFreezer {
  var $table = array();  // array of hash pairs
  var $counter;          // counter
  var $text;             // & text


  

  function setText(& $text) {
    $this->text = & $text;
    $this->table = array();
    $this->counter = 0;

    // delete all control characters (used by TexyFreezer) from input string
    $this->text = strtr($this->text, "\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F", '           ');
  }



  // create new unique key for string $str
  // and saves pair (key => str) into table $this->table
  function add($s) {
    if (is_array($s)) $s = $s[0];  // for preg_replace_callback
    $key = "\x1A" . strtr(base_convert(++$this->counter, 10, 5), '01234', "\x1B\x1C\x1D\x1E\x1F") . "\x1A";
    $this->table[$key] = $s;
    return $key; // replace by hash
  }



  // turn all hashes back to strings
  function revert() {
    $this->text = strtr($this->text, $this->table);
  }


  // static
  function freezeSpaces($s) {
    return strtr($s, " \t\r\n", "\x16\x17\x18\x19");
  }


  // static
  function unfreezeSpaces() {
    $this->text = strtr($this->text, "\x16\x17\x18\x19", " \t\r\n");
  }


 
} // TexyFreezer












  


/**
 * INTERNAL BLOCK STRUCTURE
 * ------------------------
 */
class TexyBlock {
  var $text;      // text splited in array of lines
  var $offset;
  var $children;  // output - array of lines

  var $modifier;  // TexyModifier
  var $modifierJustUpdated;


  function TexyBlock(&$texy, &$text) {
    $this->text = & $text;
    $this->offset = 0;
    $this->children = array();
    $this->modifier = & new TexyModifier($texy);
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

} // TexyBlock











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
  var $id;
  var $classes = array();
  var $hAlign;
  var $vAlign;
  var $title;
  var $styles = array();
  

  function TexyModifier(& $texy) {
    $this->texy = & $texy;
  }

  
  
  function setProps() {
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

    if ($classes && $this->texy->allowClasses) {
      foreach (explode(' ', trim($classes)) as $value) {
        if (substr($value, 0, 1) == '#') $this->id = substr($value, 1);
        else $this->classes[] = $value;
      }
    }


    if ($styles && $this->texy->allowStyles) {
      foreach (explode(';', $styles) as $value) {
        $pair = explode(':', $value);
        if (count($pair) != 2) continue;
        if ($pair1 = trim($pair[1])) $this->styles[trim($pair[0])] = $pair1;
      }
    }

    switch ($this->hAlign) {
      case TEXY_HALIGN_LEFT:    $this->styles['text-align'] = 'left'; break;
      case TEXY_HALIGN_RIGHT:   $this->styles['text-align'] = 'right'; break;
      case TEXY_HALIGN_CENTER:  $this->styles['text-align'] = 'center'; break;
      case TEXY_HALIGN_JUSTIFY: $this->styles['text-align'] = 'justify'; break;
    }

    switch ($this->vAlign) {
      case TEXY_VALIGN_TOP:     $this->styles['vertical-align'] = 'top'; break;
      case TEXY_VALIGN_MIDDLE:  $this->styles['vertical-align'] = 'middle'; break;
      case TEXY_VALIGN_BOTTOM:  $this->styles['vertical-align'] = 'bottom'; break;
    }
  }


  function clear() {
    $this->id = null;
    $this->classes = array();
    $this->hAlign = null;
    $this->vAlign = null;
    $this->title = null;
    $this->styles = array();
  }
  

  function copyFrom(&$modifier) {
    $this->id = $modifier->id;
    $this->classes = $modifier->classes;
    $this->hAlign = $modifier->hAlign;
    $this->vAlign = $modifier->vAlign;
    $this->title = $modifier->title;
    $this->styles = $modifier->styles;
  }


  // generate elements attributes
  function toAttributes() {
    $style = '';
    foreach ($this->styles as $key => $value)
      $style .= $key . ':' . $value . ';';

    return array('id'    => $this->id,
                 'class' => implode(' ', $this->classes), 
                 'title' => $this->title, 
                 'style' => $style
                );
  }


} // TexyModifier











/**
 * INTERNAL - FORMAT FOR OUTPUT
 * ----------------------------
 *
 * Useful for "folding" some substrings into unique keys
 * Key consist of unique chars \x15-\x1F   (21 - 31)
 *
 */
class TexyFormatter {
  var $baseIndent  = 0;               // indent for top elements
  var $indentSpace = "\t";            // indent space
  var $lineWrap    = 80;              // line width, doesn't include indent space

  // internal
  var $text;
  var $tagStack;
  var $emptyElements = array('img', 'hr', 'br', 'input', 'meta');
  var $blockElements = array('div', 'table', 'tr', 'dl', 'ol', 'ul', 'blockquote', 'p', 'li', 'dt', 'dd', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6');




  function TexyFormatter() {
    $this->emptyElements = array_flip($this->emptyElements);
    $this->blockElements = array_flip($this->blockElements);
  }

 
  
  
  function reformat(& $text) {
    $this->text = & $text;
    $this->wellForm($text);
    $this->indent($text);
  }



  function wellForm(&$text) {
    $this->tagStack = array();
    $text = preg_replace_callback('#<(/?)([a-z0-9]+)(>|\s.*>)()#Uis', array(&$this, '_callbackWellForm'), $text);
    if ($this->tagStack) {
      $pair = end($this->tagStack);
      while ($pair !== false) {
        $text .= '</'.$pair[0].'>';
        $pair = prev($this->tagStack);
      }
    }
  }
  

  
  function _callbackWellForm(&$matches) {
    list($match, $mClosing, $mTag, $mAttr) = $matches;
    //    [1] => /
    //    [2] => h1
    //    [3] => ...>

    $mTag = strtolower($mTag);

    if (isset($this->emptyElements[$mTag])) return $match;

    if ($mClosing) {  // closing
      $pair = end($this->tagStack);
      $s = '';
      $i = 1;
      while ($pair !== false) {
        $s .= '</'.$pair[0].'>';
        if ($pair[0] == $mTag) break;
        $pair = prev($this->tagStack);
        $i++;
      }
      if ($pair[0] <> $mTag) return '';

      if (isset($this->blockElements[$mTag])) {
        array_splice($this->tagStack, -$i);
        return $s;
      }

      unset($this->tagStack[key($this->tagStack)]);
      $pair = current($this->tagStack);
      while ($pair !== false) {
        $s .= '<'.$pair[0].$pair[1];
        $pair = next($this->tagStack);
      }
      return $s;
    } else {            // opening
      $this->tagStack[] = array($mTag, $mAttr);
    }

    return $match;
  }




  function indent(&$text) {
    $tagFreezer = &new TexyFreezer();
    $tagFreezer->setText($text);
    $text = preg_replace_callback('#<(pre|textarea|script|style)(.*)</\\1>#Ui', array(&$tagFreezer, 'add'), $text);
    $this->_indent = $this->baseIndent;
    $text = str_replace(TEXY_NEWLINE, '', $text);
    $text = preg_replace_callback('#<(/?)(div|table|tr|td|th|dl|ol|ul|blockquote|p|li|dt|dd|h1|h2|h3|h4|h5|h6|hr|br)(>| .*>)#U', array(&$this, '_callbackReformat'), $text);
    $text = preg_replace('#(?<=\S)\t+#m', '', $text);
    $text = preg_replace_callback('#^(\t*)(.*)$#m', array(&$this, '_callbackWrapLines'), $text);
    $tagFreezer->revert();
  }




  // output formating rutine - insert \n + spaces
  function _callbackReformat(&$matches) {
    list($match, $mClosing, $mTag) = $matches;
    //    [1] => /  (opening or closing element)
    //    [2] => element
    //    [3] => attributes>

    if ($mClosing == '/') {
      return str_repeat("\t", --$this->_indent) . $match . TEXY_NEWLINE;
    } else {
      if ($mTag == 'hr') return TEXY_NEWLINE . str_repeat("\t", $this->_indent) . $match . TEXY_NEWLINE;
      if (isset($this->emptyElements[$mTag])) $this->_indent--;
      return TEXY_NEWLINE . str_repeat("\t", max(0, $this->_indent++)) . $match;
    }
  }




  // output formating rutine - wrap lines
  function _callbackWrapLines(&$matches) {
    list($match, $mSpace, $mContent) = $matches;
    return $mSpace . str_replace(TEXY_NEWLINE, TEXY_NEWLINE.$mSpace, wordwrap($mContent, $this->lineWrap));
  }

 

} // TexyFormatter







require_once('texy-constants.php');      // regular expressions & other constants
require_once('texy-element.php');        // Texy! DOM element's base class
require_once('texy-elements-html.php');  // DOM elements representing HTML elements
require_once('texy-elements-other.php'); // other DOM elements
require_once('texy-module.php');         // Texy! module base class
require_once('texy-modules-pre.php');   // post modules
require_once('texy-modules-block.php');  // block modules
require_once('texy-modules-inline.php'); // inline modules
require_once('texy-modules-post.php');   // post modules
    


  

?>