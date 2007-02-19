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
            $el = TexyHtml::el($this->tag);
            $this->modifier->decorate($el);
            $tags[$this->tag] = $el;
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

        $start = $end = '';

        foreach ($tags as $el) {
            $start .= $el->startTag();
            $end = $el->endTag() . $end;
        }
        return $start . $this->generateContent() . $end;
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
        return htmlspecialChars($this->content);
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
            foreach ($tags as $el)
                $s .= $el->startTag();
        return $s;
    }

    public function closing()
    {
        $this->generateTags($tags);
        $s = '';
        if ($tags)
            foreach ($tags as $el)
                $s = $el->endTag() . $s;
        return $s;
    }


} // TexyInlineTagElement

