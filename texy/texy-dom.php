<?php

/**
 * -----------------------------------
 *   TEXY! DOM ELEMENTS BASE CLASSES
 * -----------------------------------
 *
 * Version 1 Release Candidate
 *
 * Copyright (c) 2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * Elements of Texy! "DOM"
 *
 * For the full copyright and license information, please view the COPYRIGHT
 * file that was distributed with this source code. If the COPYRIGHT file is
 * missing, please visit the Texy! homepage: http://www.texy.info
 *
 * @package Texy
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
    var $modifier;
    var $tag;


    // constructor
    function TexyHTMLElement(&$texy)
    { // $parentModule = null, maybe in PHP5
        $this->texy = & $texy;
//    $this->parentModule = & $parentModule;
        $this->modifier = & $texy->createModifier();
    }



    function generateTags(&$tags, $defaultTag = null)
    {
        $tags = (array) $tags;
        if (!$defaultTag) {
            if (!$this->tag) return;
            $defaultTag = $this->tag;
        }

        $attr['id']    = $this->modifier->id;
        $attr['title'] = $this->modifier->title;
        $attr['class'] = $this->modifier->classes;
        $attr['style'] = $this->modifier->styles;
        if ($this->modifier->hAlign) $attr['style']['text-align'] = $this->modifier->hAlign;
        if ($this->modifier->vAlign) $attr['style']['vertical-align'] = $this->modifier->vAlign;

        $tags[$defaultTag] = $attr;
    }


    // abstract
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
class TexyTextualElement extends TexyHTMLElement {
    var $children    = array();      // of TexyTextualElement

    var $contentType = TEXY_CONTENT_NONE;
    var $content;                    // string
    var $htmlSafe    = false;        // is content HTML-safe?




    function setContent($text, $isHtmlSafe = false)
    {
        $this->content = $text;
        $this->htmlSafe = $isHtmlSafe;
    }



    function safeContent($onlyReturn = false)
    {
        $safeContent = $this->htmlSafe ? $this->content : htmlSpecialChars($this->content, ENT_NOQUOTES);

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



    /***
     * Parse $text as SINGLE LINE and create string $content and array of Texy DOM elements ($children)
     ***/
    function parse($text, $postProcess = true)
    {
        $lineParser = &new TexyLineParser($this);
        $lineParser->parse($text, $postProcess);
    }



    function broadcast()
    {
        parent::broadcast();

        // apply to all children
        foreach (array_keys($this->children) as $key)
            $this->children[$key]->broadcast();
    }




    function addTo(&$ownerElement)
    {
        $key = Texy::hashKey($this->contentType);
        $ownerElement->children[$key]  = &$this;
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
    var $contentType = TEXY_CONTENT_NONE;
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



    function addTo(&$ownerElement, $elementContent = null)
    {
        $keyOpen  = Texy::hashKey($this->contentType, true);
        $keyClose = Texy::hashKey($this->contentType, false);

        $ownerElement->children[$keyOpen]  = &$this;
        $ownerElement->children[$keyClose] = &$this;
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





    /***
     * Convert DOM structure to (X)HTML code
     * and call all post-processing modules
     * @return string
     ***/
    function toHTML()
    {
        $html = parent::toHTML();

            ///////////   POST-PROCESS
        foreach ($this->texy->modules as $name => $foo)
            $this->texy->modules->$name->postProcess($html);

            ///////////   UNFREEZE SPACES
        $html = Texy::unfreezeSpaces($html);

            // THIS NOTICE SHOULD REMAIN!
        if (!defined('TEXY_NOTICE_SHOWED')) {
            $html .= "\n<!-- generated by Texy! -->";
            define('TEXY_NOTICE_SHOWED', true);
        }

        return $html;
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
            ///////////   REMOVE SPECIAL CHARS AND LINE ENDINGS
        $text = Texy::wash($text);
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
        $html = parent::toHTML();
        $html = Texy::unfreezeSpaces($html);
        return $html;
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

?>