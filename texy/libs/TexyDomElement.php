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
 * Texy DOM element base class
 */
abstract class TexyDomElement
{
    /** @var Texy */
    public $texy;

    /** @var array of TexyHtmlEl */
    public $tags = array();



    public function __construct($texy)
    {
        $this->texy = $texy;
    }



    /**
     * Returns element's content
     * @return string
     */
    abstract protected function generateContent();



    /**
     * Converts to "HTML" string
     * @return string
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

}









/**
 * This element represents array of TexyDomElement
 */
class TexyBlockElement extends TexyDomElement
{
    /** @var array of TexyDomElement */
    public $children = array();



    protected function generateContent()
    {
        $s = '';
        foreach ($this->children as $child)
            $s .= $child->__toString();

        return $s;
    }



    /**
     * Parses text as block
     * @param string
     * @return void
     */
    public function parse($text)
    {
        $parser = new TexyBlockParser($this);
        $parser->parse($text);
    }

}










/**
 * This element represents one paragraph of text
 */
class TexyTextualElement extends TexyDomElement
{
    /** @var string */
    public $content = '';



    protected function generateContent()
    {
        return htmlspecialChars($this->content, ENT_NOQUOTES);
    }



    /**
     * Parses text as single line
     * @param string
     * @return void
     */
    public function parse($text)
    {
        $parser = new TexyLineParser($this);
        $parser->parse($text);
    }

}





/**
 * Generic paragraph / div / transparent created by TexyGenericBlock
 */
class TexyParagraphElement extends TexyTextualElement
{
}
