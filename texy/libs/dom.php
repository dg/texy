<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://www.texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 * @package    Texy
 * @category   Text
 * @version    1.0 for PHP4 & PHP5 (released 2006/04/18)
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * Texy! ELEMENT BASE CLASS
 * ------------------------
 */
class TexyDOMElement {
    var $texy; // parent Texy! object
    var $hidden;
    var $contentType = TEXY_CONTENT_NONE;


    function __construct(&$texy)
    {
        $this->texy = & $texy;
    }


    /**
     * PHP4 compatible constructor
     * @see http://www.dgx.cz/trine/item/how-to-emulate-php5-object-model-in-php4
     */
    function TexyDOMElement(&$texy)
    {
        // generate references
        if (PHP_VERSION < 5) foreach ($this as $key => $foo) $GLOBALS['$$HIDDEN$$'][] = & $this->$key;

        // call PHP5 constructor
        call_user_func_array(array(&$this, '__construct'), array(&$texy));
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
    var $modifier;
    var $tag;


    // constructor
    function __construct(&$texy)
    {
        $this->texy = & $texy;
        $this->modifier = &new TexyModifier($texy);
    }



    function generateTags(&$tags)
    {
        $tags = (array) $tags;

        if ($this->tag) {
            $attrs = $this->modifier->getAttrs($this->tag);
            $attrs['id']    = $this->modifier->id;
            if ($this->modifier->title !== NULL)
                $attrs['title'] = $this->modifier->title;
            $attrs['class'] = $this->modifier->classes;
            $attrs['style'] = $this->modifier->styles;
            if ($this->modifier->hAlign) $attrs['style']['text-align'] = $this->modifier->hAlign;
            if ($this->modifier->vAlign) $attrs['style']['vertical-align'] = $this->modifier->vAlign;

            $tags[$this->tag] = $attrs;
        }
    }


    function generateContent() { }


    // convert element to HTML string
    function toHTML()
    {
        $this->generateTags($tags);
        if ($this->hidden) return;

        return TexyHTML::openingTags($tags)
                     . $this->generateContent()
                     . TexyHTML::closingTags($tags);
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
    var $children = array(); // of TexyBlockElement




    function appendChild(&$child)
    {
        $this->children[] = &$child;
        $this->contentType = max($this->contentType, $child->contentType);
    }


    function generateContent()
    {
        $html = '';
        foreach (array_keys($this->children) as $key)
            $html .= $this->children[$key]->toHTML();

        return $html;
    }





    /**
     * Parse $text as BLOCK and create array children (array of Texy DOM elements)
     */
    function parse($text)
    {
        $parser = &new TexyBlockParser($this);
        $parser->parse($text);
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
class TexyTextualElement extends TexyBlockElement {
    var $content;                    // string
    var $htmlSafe    = FALSE;        // is content HTML-safe?




    function setContent($text, $isHtmlSafe = FALSE)
    {
        $this->content = $text;
        $this->htmlSafe = $isHtmlSafe;
    }



    function safeContent($onlyReturn = FALSE)
    {
        $safeContent = $this->htmlSafe ? $this->content : htmlSpecialChars($this->content, ENT_NOQUOTES);

        if ($onlyReturn) return $safeContent;
        else {
            $this->htmlSafe = TRUE;
            return $this->content = $safeContent;
        }
    }




    function generateContent()
    {
        $content = $this->safeContent(TRUE);

        if ($this->children) {
            $table = array();
            foreach (array_keys($this->children) as $key)
                $table[$key] = $this->children[$key]->toHTML( TexyTextualElement::isHashOpening($key) );

            return strtr($content, $table);
        }

        return $content;
    }



    /**
     * Parse $text as SINGLE LINE and create string $content and array of Texy DOM elements ($children)
     */
    function parse($text, $postProcess = TRUE)
    {
        $parser = &new TexyLineParser($this);
        $parser->parse($text, $postProcess);
    }




    /**
     * Generate unique HASH key - useful for freezing (folding) some substrings
     * Key consist of unique chars \x19, \x1B-\x1E (noncontent) (or \x1F detect opening tag)
     *                             \x1A, \x1B-\x1E (with content)
     * @return string
     * @static
     */
    function hashKey($contentType = NULL, $opening = NULL)
    {
        $border = ($contentType == TEXY_CONTENT_NONE) ? "\x19" : "\x1A";
        return $border . ($opening ? "\x1F" : "") . strtr(base_convert(count($this->children), 10, 4), '0123', "\x1B\x1C\x1D\x1E") . $border;
    }


    /**
     *
     */
    function isHashOpening($hash)
    {
        return $hash{1} == "\x1F";
    }



    function appendChild(&$child, $innerText = NULL)
    {
        $this->contentType = max($this->contentType, $child->contentType);

        if (is_a($child, 'TexyInlineTagElement')) {
            $keyOpen  = $this->hashKey($child->contentType, TRUE);
            $keyClose = $this->hashKey($child->contentType, FALSE);

            $this->children[$keyOpen]  = &$child;
            $this->children[$keyClose] = &$child;
            return $keyOpen . $innerText . $keyClose;
        }

        $key = $this->hashKey($child->contentType);
        $this->children[$key] = &$child;
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
    var $_closingTag;



    // convert element to HTML string
    function toHTML($opening)
    {
        if ($opening) {
            $this->generateTags($tags);
            if ($this->hidden) return;
            $this->_closingTag = TexyHTML::closingTags($tags);
            return TexyHTML::openingTags($tags);

        } else {
            return $this->_closingTag;
        }
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


    /**
     * Convert Texy! document into DOM structure
     * Before converting it normalize text and call all pre-processing modules
     */
    function parse($text)
    {
            ///////////   REMOVE SPECIAL CHARS, NORMALIZE LINES
        $text = Texy::wash($text);

            ///////////   STANDARDIZE LINE ENDINGS TO UNIX-LIKE  (DOS, MAC)
        $text = str_replace("\r\n", TEXY_NEWLINE, $text); // DOS
        $text = str_replace("\r", TEXY_NEWLINE, $text); // Mac

            ///////////   REPLACE TABS WITH SPACES
        $tabWidth = $this->texy->tabWidth;
        while (strpos($text, "\t") !== FALSE)
            $text = preg_replace_callback('#^(.*)\t#mU',
                       create_function('&$matches', "return \$matches[1] . str_repeat(' ', $tabWidth - strlen(\$matches[1]) % $tabWidth);"),
                       $text);

            ///////////   REMOVE TEXY! COMMENTS
        $commentChars = $this->texy->utf ? "\xC2\xA7" : "\xA7";
        $text = preg_replace('#'.$commentChars.'{2,}(?!'.$commentChars.').*('.$commentChars.'{2,}|$)(?!'.$commentChars.')#mU', '', $text);

            ///////////   RIGHT TRIM
        $text = preg_replace("#[\t ]+$#m", '', $text); // right trim


            ///////////   PRE-PROCESSING
        foreach ($this->texy->modules as $id => $foo)
            $this->texy->modules[$id]->preProcess($text);

            ///////////   PROCESS
        parent::parse($text);
    }





    /**
     * Convert DOM structure to (X)HTML code
     * and call all post-processing modules
     * @return string
     */
    function toHTML()
    {
        $html = parent::toHTML();

            ///////////   POST-PROCESS
        foreach ($this->texy->modules as $id => $foo)
            $this->texy->modules[$id]->postProcess($html);

            ///////////   UNFREEZE SPACES
        $html = Texy::unfreezeSpaces($html);

            // THIS NOTICE SHOULD REMAIN!
        if (!defined('TEXY_NOTICE_SHOWED')) {
            $html .= "\n<!-- generated by Texy! -->";
            define('TEXY_NOTICE_SHOWED', TRUE);
        }

        return $html;
    }





    /**
     * Build list for easy access to DOM structure
     */
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


    /**
     * Convert Texy! single line into DOM structure
     */
    function parse($text)
    {
            ///////////   REMOVE SPECIAL CHARS AND LINE ENDINGS
        $text = Texy::wash($text);
        $text = rtrim(strtr($text, array("\n" => ' ', "\r" => '')));

            ///////////   PROCESS
        parent::parse($text);
    }





    /**
     * Convert DOM structure to (X)HTML code
     * @return string
     */
    function toHTML()
    {
        $html = parent::toHTML();
        $html = Texy::unfreezeSpaces($html);
        return $html;
    }




    /**
     * Build list for easy access to DOM structure
     */
    function buildLists()
    {
        $this->elements = array();
        $this->elementsById = array();
        $this->elementsByClass = array();
        $this->broadcast();
    }



} // TexyDOMLine

?>