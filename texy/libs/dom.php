<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    $Revision$ $Date$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * DOM element base class
 * @abstract
 */
abstract class TexyDomElement
{
    const CONTENT_NONE =    1;
    const CONTENT_TEXTUAL = 2;
    const CONTENT_BLOCK =   3;

    public $texy; // parent Texy! object
    public $contentType = TexyDomElement::CONTENT_NONE;
    public $behaveAsOpening; // !!!


    public function __construct($texy)
    {
        $this->texy = $texy;
    }



    /**
     * Convert element to HTML string
     * @abstract
     */
    abstract public function toHtml();




    // for easy Texy! DOM manipulation
    protected function broadcast()
    {
        // build DOM->elements list
        $this->texy->DOM->elements[] = $this;
    }


    /**
     * Undefined property usage prevention
     */
    function __set($nm, $val)     { $c=get_class($this); trigger_error("Undefined property '$c::$$nm'", E_USER_ERROR); }
    function __get($nm)           { $c=get_class($this); trigger_error("Undefined property '$c::$$nm'", E_USER_ERROR); }
    private function __unset($nm) { $c=get_class($this); trigger_error("Undefined property '$c::$$nm'", E_USER_ERROR); }
    private function __isset($nm) { return FALSE; }

}  // TexyDomElement








/**
 * This elements represents one HTML element
 * @abstract
 */
class TexyHtmlElement extends TexyDomElement
{
    public $modifier;
    public $tag;


    // constructor
    public function __construct($texy)
    {
        $this->texy =  $texy;
        $this->modifier = new TexyModifier($texy);
    }



    /**
     * Generate HTML element tags
     */
    protected function generateTags(&$tags)
    {
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
    protected function generateContent() { }



    /**
     * Convert element to HTML string
     */
    public function toHtml()
    {
        $this->generateTags($tags);

        return TexyHtml::openingTags($tags)
                     . $this->generateContent()
                     . TexyHtml::closingTags($tags);
    }



    protected function broadcast()
    {
        parent::broadcast();

        // build $texy->DOM->elementsById list
        if ($this->modifier->id)
            $this->texy->DOM->elementsById[$this->modifier->id] = $this;

        // build $texy->DOM->elementsByClass list
        if ($this->modifier->classes)
            foreach ($this->modifier->classes as $class)
                $this->texy->DOM->elementsByClass[$class][] = $this;
    }


}  // TexyHtmlElement











/**
 * This element represent array of other blocks (TexyHtmlElement)
 *
 */
class TexyBlockElement extends TexyHtmlElement
{
    protected $children = array();




    // $child must be TexyBlockElement or TexyTextualElement
    public function appendChild($child)
    {
/* !!!
        if (!($child instanceof TexyBlockElement) && !($child instanceof TexyTextualElement))
            die('Only TexyInlineTagElement allowed.');
*/
        $this->children[] = $child;
        $this->contentType = max($this->contentType, $child->contentType);
    }

    public function getChild($key)
    {
        if (isset($this->children[$key]))
           return $this->children[$key];
    }

    protected function generateContent()
    {
        $html = '';
        foreach ($this->children as $child)
            $html .= $child->toHtml();

        return $html;
    }





    /**
     * Parse $text as BLOCK and create array children (array of Texy DOM elements)
     */
    public function parse($text)
    {
        $parser = new TexyBlockParser($this);
        $parser->parse($text);
    }



    protected function broadcast()
    {
        parent::broadcast();

        // apply to all children
        foreach ($this->children as $child)
            $child->broadcast();
    }

}  // TexyBlockElement










/**
 * This element represent one line of text.
 * Text represents $content and $children is array of TexyInlineTagElement
 *
 */
class TexyTextualElement extends TexyBlockElement
{
    public $content;                    // string
    protected $htmlSafe = FALSE;        // is content HTML-safe?




    public function setContent($text, $isHtmlSafe = FALSE)
    {
        $this->content = $text;
        $this->htmlSafe = $isHtmlSafe;
    }


    public function getContent()
    {
        return $this->content;
    }


    public function safeContent($onlyReturn = FALSE)
    {
        $safeContent = $this->htmlSafe ? $this->content : TexyHtml::htmlChars($this->content);

        if ($onlyReturn) return $safeContent;
        else {
            $this->htmlSafe = TRUE;
            return $this->content = $safeContent;
        }
    }




    protected function generateContent()
    {
        $content = $this->safeContent(TRUE);

        if ($this->children) {
            $table = array();
            foreach ($this->children as $key => $child) {
                $child->behaveAsOpening = Texy::isHashOpening($key);
                $table[$key] = $child->toHtml();
            }

            return strtr($content, $table);
        }

        return $content;
    }



    /**
     * Parse $text as SINGLE LINE and create string $content and array of Texy DOM elements ($children)
     */
    public function parse($text)
    {
        $parser = new TexyLineParser($this);
        $parser->parse($text);
    }




    /**
     * Generate unique HASH key - useful for freezing (folding) some substrings
     * Key consist of unique chars \x19, \x1B-\x1E (noncontent) (or \x1F detect opening tag)
     *                             \x1A, \x1B-\x1E (with content)
     * @return string
     * @static
     */
    protected function hashKey($contentType = NULL, $opening = NULL)
    {
        $border = ($contentType == self::CONTENT_NONE) ? "\x19" : "\x1A";
        return $border . ($opening ? "\x1F" : "") . strtr(base_convert(count($this->children), 10, 4), '0123', "\x1B\x1C\x1D\x1E") . $border;
    }


    /**
     *
     */
    protected function isHashOpening($hash)
    {
        return $hash{1} == "\x1F";
    }



    public function appendChild($child, $innerText = NULL)
    {
        $this->contentType = max($this->contentType, $child->contentType);

        if ($child instanceof TexyInlineTagElement) {
            $keyOpen  = $this->hashKey($child->contentType, TRUE);
            $keyClose = $this->hashKey($child->contentType, FALSE);

            $this->children[$keyOpen]  = $child;
            $this->children[$keyClose] = $child;
            return $keyOpen . $innerText . $keyClose;
        }

        $key = $this->hashKey($child->contentType);
        $this->children[$key] = $child;
        return $key;
    }




}  // TexyTextualElement







/**
 * Represent HTML tags (elements without content)
 * Used as children of TexyTextualElement
 *
 */
class TexyInlineTagElement extends TexyHtmlElement
{
    private $closingTag;



    // convert element to HTML string
    public function toHtml()
    {
        if ($this->behaveAsOpening) {
            $this->generateTags($tags);
            $this->closingTag = TexyHtml::closingTags($tags);
            return TexyHtml::openingTags($tags);

        } else {
            return $this->closingTag;
        }
    }





} // TexyInlineTagElement

















/**
 * Texy! DOM
 * ---------
 */
class TexyDom extends TexyBlockElement
{
    public $elements;
    public $elementsById;
    public $elementsByClass;


    /**
     * Convert Texy! document into DOM structure
     * Before converting it normalize text and call all pre-processing modules
     */
    public function parse($text)
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
                       create_function('$matches', "return \$matches[1] . str_repeat(' ', $tabWidth - strlen(\$matches[1]) % $tabWidth);"),
                       $text);

            ///////////   REMOVE TEXY! COMMENTS
        $commentChars = $this->texy->utf ? "\xC2\xA7" : "\xA7";
        $text = preg_replace('#'.$commentChars.'{2,}(?!'.$commentChars.').*('.$commentChars.'{2,}|$)(?!'.$commentChars.')#mU', '', $text);

            ///////////   RIGHT TRIM
        $text = preg_replace("#[\t ]+$#m", '', $text); // right trim


            ///////////   PRE-PROCESSING
        foreach ($this->texy->getModules() as $module)
            $text = $module->preProcess($text);

            ///////////   PROCESS
        parent::parse($text);
    }





    /**
     * Convert DOM structure to (X)HTML code
     * and call all post-processing modules
     * @return string
     */
    public function toHtml()
    {
        $html = parent::toHtml();

        $obj = new TexyHtmlWellForm();
        $html = $obj->process($html);

            ///////////   POST-PROCESS
        foreach ($this->texy->getModules() as $module)
            $html = $module->postProcess($html);

            ///////////   UNFREEZE SPACES
        $html = Texy::unfreezeSpaces($html);

        $html = TexyHtml::checkEntities($html);

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
    public function buildLists()
    {
        $this->elements = array();
        $this->elementsById = array();
        $this->elementsByClass = array();
        $this->broadcast();
    }



}  // TexyDom











/**
 * Texy! DOM for single line
 * -------------------------
 */
class TexyDomLine extends TexyTextualElement
{
    public  $elements;
    public  $elementsById;
    public  $elementsByClass;


    /**
     * Convert Texy! single line into DOM structure
     */
    public function parse($text)
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
    public function toHtml()
    {
        $html = parent::toHtml();
        $wf = new TexyHtmlWellForm();
        $html = $wf->process($html);
        $html = Texy::unfreezeSpaces($html);
        $html = TexyHtml::checkEntities($html);
        return $html;
    }




    /**
     * Build list for easy access to DOM structure
     */
    public function buildLists()
    {
        $this->elements = array();
        $this->elementsById = array();
        $this->elementsByClass = array();
        $this->broadcast();
    }



} // TexyDomLine
