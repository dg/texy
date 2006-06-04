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
 * @version    1.2 for PHP4 & PHP5 (released 2006/06/01)
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * DOM element base class
 * @abstract
 */
class TexyDOMElement
{
    var $texy; // parent Texy! object
    var $contentType = TEXY_CONTENT_NONE;


    function __construct(&$texy)
    {
        $this->texy = & $texy;
    }


    /**
     * PHP4-only constructor
     * @see http://www.dgx.cz/trine/item/how-to-emulate-php5-object-model-in-php4
     */
    function TexyDOMElement(&$texy)
    {
        // generate references
        foreach ($this as $key => $foo) $GLOBALS['$$HIDDEN$$'][] = & $this->$key;

        // call PHP5 constructor
        call_user_func_array(array(&$this, '__construct'), array(&$texy));
    }



    /**
     * Convert element to HTML string
     * @abstract
     */
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
 * This elements represents one HTML element
 * @abstract
 */
class TexyHTMLElement extends TexyDOMElement
{
    var $modifier;
    var $tag;


    // constructor
    function __construct(&$texy)
    {
        $this->texy = & $texy;
        $this->modifier = &new TexyModifier($texy);
    }



    /**
     * Generate HTML element tags
     */
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



    /**
     * Generate HTML element content
     * @abstract
     */
    function generateContent() { }



    /**
     * Convert element to HTML string
     */
    function toHTML()
    {
        $this->generateTags($tags);

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
 * This element represent array of other blocks (TexyHTMLElement)
 *
 */
class TexyBlockElement extends TexyHTMLElement
{
    var $_children = array();




    // $child must be TexyBlockElement or TexyTextualElement
    function appendChild(&$child)
    {
        if (!is_a($child, 'TexyBlockElement') && !is_a($child, 'TexyTextualElement'))
            die('Only TexyInlineTagElement allowed.');

        $this->_children[] = &$child;
        $this->contentType = max($this->contentType, $child->contentType);
    }


    function generateContent()
    {
        $html = '';
        foreach (array_keys($this->_children) as $key)
            $html .= $this->_children[$key]->toHTML();

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
        foreach (array_keys($this->_children) as $key)
            $this->_children[$key]->broadcast();
    }

}  // TexyBlockElement










/**
 * This element represent one line of text.
 * Text represents $content and $children is array of TexyInlineTagElement
 *
 */
class TexyTextualElement extends TexyHTMLElement
{
    var $_children = array();
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

        if ($this->_children) {
            $table = array();
            foreach (array_keys($this->_children) as $key) {
                $this->_children[$key]->behaveAsOpening = TexyTextualElement::isHashOpening($key);
                $table[$key] = $this->_children[$key]->toHTML();
            }

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
        return $border . ($opening ? "\x1F" : "") . strtr(base_convert(count($this->_children), 10, 4), '0123', "\x1B\x1C\x1D\x1E") . $border;
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

            $this->_children[$keyOpen]  = &$child;
            $this->_children[$keyClose] = &$child;
            return $keyOpen . $innerText . $keyClose;
        }

        $key = $this->hashKey($child->contentType);
        $this->_children[$key] = &$child;
        return $key;
    }



    function broadcast()
    {
        parent::broadcast();

        // apply to all children
        foreach (array_keys($this->_children) as $key)
            $this->_children[$key]->broadcast();
    }


}  // TexyTextualElement







/**
 * Represent HTML tags (elements without content)
 * Used as children of TexyTextualElement
 *
 */
class TexyInlineTagElement extends TexyHTMLElement
{
    var $behaveAsOpening;
    var $_closingTag;



    // convert element to HTML string
    function toHTML()
    {
        if ($this->behaveAsOpening) {
            $this->generateTags($tags);
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
class TexyDOM extends TexyBlockElement
{
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

        $wf = new TexyWellForm();
        $html = $wf->process($html);

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
class TexyDOMLine extends TexyTextualElement
{
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
        $wf = new TexyWellForm();
        $html = $wf->process($html);
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