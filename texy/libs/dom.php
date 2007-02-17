<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2007 David Grudl
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
    const CONTENT_INLINE =  2;
    const CONTENT_TEXTUAL = 3;
    const CONTENT_BLOCK =   4;

    public $texy; // parent Texy! object
    public $modifier;
    public $tag;


    public function __construct($texy)
    {
        $this->texy = $texy;
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
    public function __toString()
    {
        $tags = array();
        $this->generateTags($tags);

        return TexyHtml::openingTags($tags)
                     . $this->generateContent()
                     . TexyHtml::closingTags($tags);
    }




    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }
    private function __unset($nm) { $this->__get($nm); }
    private function __isset($nm) { $this->__get($nm); }

}  // TexyDomElement









/**
 * This element represent array of other blocks (TexyDomElement)
 *
 */
class TexyBlockElement extends TexyDomElement
{
    protected $children = array();




    // $child must be TexyBlockElement or TexyTextualElement
    public function appendChild($child)
    {
        if (!($child instanceof TexyBlockElement) && !($child instanceof TexyTextualElement))
            die('Only TexyInlineTagElement allowed.');

        $this->children[] = $child;
    }

    public function getChild($key)
    {
        if (isset($this->children[$key]))
           return $this->children[$key];
        return NULL;
    }

    protected function generateContent()
    {
        $html = '';
        foreach ($this->children as $child)
            $html .= $child->__toString();

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



}  // TexyBlockElement










/**
 * This element represent one line of text.
 * Text represents $content and $children is array of TexyInlineTagElement
 *
 */
class TexyTextualElement extends TexyDomElement
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
        $content = $this->texy->hashReplace($content);
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



}  // TexyTextualElement







/**
 * Represent HTML tags (elements without content)
 * Used as children of TexyTextualElement
 *
 */
class TexyInlineTagElement extends TexyDomElement
{
    private $closingTag;
    public $behaveAsOpening;



    // convert element to HTML string
    public function __toString()
    {
        if ($this->behaveAsOpening) {
            $tags = array();
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
        $text = str_replace("\r\n", "\n", $text); // DOS
        $text = strtr($text, "\r", "\n"); // Mac

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
    public function __toString()
    {
        $html = parent::__toString();

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
    public function __toString()
    {
        $html = parent::__toString();
        $wf = new TexyHtmlWellForm();
        $html = $wf->process($html);
        $html = Texy::unfreezeSpaces($html);
        $html = TexyHtml::checkEntities($html);
        return $html;
    }



} // TexyDomLine
