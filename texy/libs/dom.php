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

        $open = $close = '';
        foreach ($tags as $tag => $attr) {
            $open .= TexyHtml::openingTag($tag, $attr);
            $close = TexyHtml::closingTag($tag) . $close;
        }
        if ($open) $open = $this->texy->hash($open, TexyDomElement::CONTENT_BLOCK);
        if ($close) $close = $this->texy->hash($close, TexyDomElement::CONTENT_BLOCK);
        return $open . $this->generateContent() . $close;
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
            die('Only TexyBlockElement or TexyTextualElement allowed.');

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
            $html .= $child->__toString() . "\n";

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
 * Text represents $content
 *
 */
class TexyTextualElement extends TexyDomElement
{
    public $content;                    // string


    protected function generateContent()
    {
        return $this->content;
    }



    public function toHtml()
    {
        $tags = array();
        $this->generateTags($tags);

        $open = $close = '';
        foreach ($tags as $tag => $attr) {
            $open .= TexyHtml::openingTag($tag, $attr);
            $close = TexyHtml::closingTag($tag) . $close;
        }
        return $open . htmlspecialChars($this->content) . $close;
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

    public function opening()
    {
        $this->generateTags($tags);
        $s = '';
        if ($tags)
            foreach ($tags as $tag => $attr)
                $s .= TexyHtml::openingTag($tag, $attr);
        return $s;
    }

    public function closing()
    {
        $this->generateTags($tags);
        $s = '';
        if ($tags)
            foreach ($tags as $tag => $attr)
                $s = TexyHtml::closingTag($tag) . $s;
        return $s;
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

        $html = htmlspecialChars($html);

        $html = $this->texy->hashReplace($html);

        $obj = new TexyHtmlWellForm();
        $html = $obj->process($html);

            ///////////   POST-PROCESS
        foreach ($this->texy->getModules() as $module)
            $html = $module->postProcess($html);

            ///////////   UNFREEZE SPACES
        $html = Texy::unfreezeSpaces($html);

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
        $html = $this->texy->hashReplace($html);

        $wf = new TexyHtmlWellForm();
        $html = $wf->process($html);
        $html = Texy::unfreezeSpaces($html);
        return $html;
    }



} // TexyDomLine
