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
    public $tags = array();


    public function __construct($texy)
    {
        $this->texy = $texy;
    }


    /**
     * Generate HTML element content
     */
    abstract protected function generateContent();


    /**
     * Convert element to HTML string
     */
    public function __toString()
    {
        $start = $end = '';
        foreach ($this->tags as $el) {
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
 * This element represents array of TexyDomElement
 */
class TexyBlockElement extends TexyDomElement
{
    public $children = array();


    protected function generateContent()
    {
        $html = '';
        foreach ($this->children as $child)
            $html .= $child->__toString();

        return $html;
    }


    /**
     * Parse text as BLOCK and create array of children
     */
    public function parse($text)
    {
        $parser = new TexyBlockParser($this);
        $parser->parse($text);
    }



}  // TexyBlockElement










/**
 * This element represents one paragraph of text.
 */
class TexyTextualElement extends TexyDomElement
{
    public $content = '';


    protected function generateContent()
    {
        return htmlspecialChars($this->content);
    }


    /**
     * Parse $text as single line and create $this->content
     */
    public function parse($text)
    {
        $parser = new TexyLineParser($this);
        $parser->parse($text);
    }

}  // TexyTextualElement






/**
 * Generic paragraph / div / transparent
 */
class TexyParagraphElement extends TexyTextualElement
{
}



