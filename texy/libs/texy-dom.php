<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://www.texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    1.1 for PHP4 & PHP5 $Date$ $Revision$
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


    // PHP5 constructor
    function __construct(&$texy)
    {
        $this->texy = & $texy;
    }


    // PHP4 constructor
    function TexyDOMElement(&$texy)
    {
        // call php5 constructor
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
        $this->modifier = & $texy->createModifier();
    }



    function generateTags(&$tags, $defaultTag = null)
    {
        $tags = (array) $tags;
        if ($defaultTag == null) {
            if ($this->tag == null) return;
            $defaultTag = $this->tag;
        }

        $attrs = $this->modifier->getAttrs($defaultTag);
        $attrs['id']    = $this->modifier->id;
        if ($this->modifier->title !== null)
            $attrs['title'] = $this->modifier->title;
        $attrs['class'] = $this->modifier->classes;
        $attrs['style'] = $this->modifier->styles;
        if ($this->modifier->hAlign) $attrs['style']['text-align'] = $this->modifier->hAlign;
        if ($this->modifier->vAlign) $attrs['style']['vertical-align'] = $this->modifier->vAlign;

        $tags[$defaultTag] = $attrs;
    }


    function generateContent() { }


    // convert element to HTML string
    function toHTML()
    {
        $this->generateTags($tags);
        if ($this->hidden) return;

        return Texy::openingTags($tags)
                     . $this->generateContent()
                     . Texy::closingTags($tags);
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
        $blockParser = &new TexyBlockParser($this);
        $blockParser->parse($text);
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
    var $htmlSafe    = false;        // is content HTML-safe?




    function setContent($text, $isHtmlSafe = false)
    {
        $this->content = $text;
        $this->htmlSafe = $isHtmlSafe;
    }



    function safeContent($onlyReturn = false)
    {
        $safeContent = $this->htmlSafe ? $this->content : Texy::htmlChars($this->content);

        if ($onlyReturn) return $safeContent;
        else {
            $this->htmlSafe = true;
            return $this->content = $safeContent;
        }
    }




    function generateContent()
    {
        $content = $this->safeContent(true);

        if ($this->children) {
            $table = array();
            foreach (array_keys($this->children) as $key)
                $table[$key] = $this->children[$key]->toHTML( Texy::isHashOpening($key) );

            return strtr($content, $table);
        }

        return $content;
    }



    /**
     * Parse $text as SINGLE LINE and create string $content and array of Texy DOM elements ($children)
     */
    function parse($text, $postProcess = true)
    {
        $lineParser = &new TexyLineParser($this);
        $lineParser->parse($text, $postProcess);
    }




    function appendChild(&$child, $innerText = NULL)
    {
        $this->contentType = max($this->contentType, $child->contentType);

        if (is_a($child, 'TexyInlineTagElement')) {
            $keyOpen  = Texy::hashKey($child->contentType, true);
            $keyClose = Texy::hashKey($child->contentType, false);

            $this->children[$keyOpen]  = &$child;
            $this->children[$keyClose] = &$child;
            return $keyOpen . $innerText . $keyClose;
        }

        $key = Texy::hashKey($child->contentType);
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
            $this->_closingTag = Texy::closingTags($tags);
            return Texy::openingTags($tags);

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
        while (strpos($text, "\t") !== false)
            $text = preg_replace_callback('#^(.*)\t#mU',
                       create_function('&$matches', "return \$matches[1] . str_repeat(' ', $tabWidth - strlen(\$matches[1]) % $tabWidth);"),
                       $text);

            ///////////   REMOVE TEXY! COMMENTS
        $commentChars = $this->texy->utf ? "\xC2\xA7" : "\xA7";
        $text = preg_replace('#'.$commentChars.'{2,}(?!'.$commentChars.').*('.$commentChars.'{2,}|$)(?!'.$commentChars.')#mU', '', $text);

            ///////////   RIGHT TRIM
        $text = preg_replace("#[\t ]+$#m", '', $text); // right trim


            ///////////   PRE-PROCESSING
        foreach ($this->texy->modules as $name => $foo)
            $this->texy->modules->$name->preProcess($text);

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
        foreach ($this->texy->modules as $name => $foo)
            $this->texy->modules->$name->postProcess($html);

            ///////////   UNFREEZE SPACES
        $html = Texy::unfreezeSpaces($html);

        $html = Texy::checkEntities($html);

            // THIS NOTICE SHOULD REMAIN!
        if (!defined('TEXY_NOTICE_SHOWED')) {
            $html .= "\n<!-- generated by Texy! -->";
            define('TEXY_NOTICE_SHOWED', true);
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
        $html = Texy::checkEntities($html);
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