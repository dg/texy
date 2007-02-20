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


// static variable initialization
TexyHtml::$valid = array_merge(TexyHtml::$block, TexyHtml::$inline);


/**
 * HTML helper
 *
 */
class TexyHtml
{
    /** @var string element's name */
    public $_name;

    /** @var bool is element empty? */
    public $_empty;

    /* element's attributes are not explicitly declared */


    // notice: I use a little trick - isset($array[$item]) is much faster than in_array($item, $array)
    static public $block = array(
        'address'=>1, 'blockquote'=>1, 'caption'=>1, 'col'=>1, 'colgroup'=>1, 'dd'=>1, 'div'=>1, 'dl'=>1, 'dt'=>1, 'fieldset'=>1, 'form'=>1,
        'h1'=>1, 'h2'=>1, 'h3'=>1, 'h4'=>1, 'h5'=>1, 'h6'=>1, 'hr'=>1, 'iframe'=>1, 'legend'=>1, 'li'=>1, 'object'=>1, 'ol'=>1, 'p'=>1,
        'param'=>1, 'pre'=>1, 'table'=>1, 'tbody'=>1, 'td'=>1, 'tfoot'=>1, 'th'=>1, 'thead'=>1, 'tr'=>1, 'ul'=>1,/*'embed'=>1,*/);
    // todo: iframe, object, are block?

    static public $inline = array(
        'a'=>1, 'abbr'=>1, 'acronym'=>1, 'area'=>1, 'b'=>1, 'big'=>1, 'br'=>1, 'button'=>1, 'cite'=>1, 'code'=>1, 'del'=>1, 'dfn'=>1,
        'em'=>1, 'i'=>1, 'img'=>1, 'input'=>1, 'ins'=>1, 'kbd'=>1, 'label'=>1, 'map'=>1, 'noscript'=>1, 'optgroup'=>1, 'option'=>1, 'q'=>1,
        'samp'=>1, 'script'=>1, 'select'=>1, 'small'=>1, 'span'=>1, 'strong'=>1, 'sub'=>1, 'sup'=>1, 'textarea'=>1, 'tt'=>1, 'var'=>1,);

    static public $inlineCont = array(
        'br'=>1,'button'=>1,'iframe'=>1,'img'=>1,'input'=>1,'object'=>1,'script'=>1,'select'=>1,'textarea'=>1,'applet'=>1,'isindex'=>1,);
    // todo: use applet, isindex?

    static public $empty = array('img'=>1, 'hr'=>1, 'br'=>1, 'input'=>1, 'meta'=>1, 'area'=>1, 'base'=>1, 'col'=>1, 'link'=>1, 'param'=>1,);

    static public $meta = array('html'=>1, 'head'=>1, 'body'=>1, 'base'=>1, 'meta'=>1, 'link'=>1, 'title'=>1,);

    static public $accepted_attrs = array(
        'abbr'=>1, 'accesskey'=>1, 'align'=>1, 'alt'=>1, 'archive'=>1, 'axis'=>1, 'bgcolor'=>1, 'cellpadding'=>1, 'cellspacing'=>1, 'char'=>1,
        'charoff'=>1, 'charset'=>1, 'cite'=>1, 'classid'=>1, 'codebase'=>1, 'codetype'=>1, 'colspan'=>1, 'compact'=>1, 'coords'=>1, 'data'=>1,
        'datetime'=>1, 'declare'=>1, 'dir'=>1, 'face'=>1, 'frame'=>1, 'headers'=>1, 'href'=>1, 'hreflang'=>1, 'hspace'=>1, 'ismap'=>1,
        'lang'=>1, 'longdesc'=>1, 'name'=>1, 'noshade'=>1, 'nowrap'=>1, 'onblur'=>1, 'onclick'=>1, 'ondblclick'=>1, 'onkeydown'=>1,
        'onkeypress'=>1, 'onkeyup'=>1, 'onmousedown'=>1, 'onmousemove'=>1, 'onmouseout'=>1, 'onmouseover'=>1, 'onmouseup'=>1, 'rel'=>1,
        'rev'=>1, 'rowspan'=>1, 'rules'=>1, 'scope'=>1, 'shape'=>1, 'size'=>1, 'span'=>1, 'src'=>1, 'standby'=>1, 'start'=>1, 'summary'=>1,
        'tabindex'=>1, 'target'=>1, 'title'=>1, 'type'=>1, 'usemap'=>1, 'valign'=>1, 'value'=>1, 'vspace'=>1,);

    static public $valid; /* array_merge(TexyHtml::$block, TexyHtml::$inline); */


    /**
     * TexyHtml element's factory
     * @param string element name (or NULL)
     * @param array  optional attributes list
     * @return TexyHtml
     */
    static public function el($name=NULL, $attrs=NULL)
    {
        return new self($name, $attrs);
    }


    private function __construct($name, $attrs)
    {
        $this->_name = $name;
        $this->_empty = isset(self::$empty[$name]);

        if (is_array($attrs)) {
           foreach ($attrs as $key => $value) $this->$key = $value;
        }
    }


    /**
     * Changes element's name
     * @param string
     * @return TexyHtml self
     */
    public function setElement($name)
    {
        $this->_name = $name;
        $this->_empty = isset(self::$empty[$name]);
        return $this;
    }


    /**
     * Overloaded setter for element's attribute
     * @param string function name
     * @param array function arguments
     * @return TexyHtml self
     */
    public function __call($m, $args)
    {
        $this->$m = $args[0];
        return $this;
    }


    /**
     * Returns element's start tag
     * @return string
     */
    public function startTag()
    {
        if (!$this->_name) return '';

        $s = '<' . $this->_name;

        // reserved properties 
    	static $res = array('_name'=>1, '_empty'=>1,);

        // use array_change_key_case($this, CASE_LOWER) ?
        // for each attribute...
        foreach ($this as $key => $value)
        {
            // skip private properties
            if (isset($res[$key])) continue;

            // skip NULLs and false boolean attributes
            if ($value === NULL || $value === FALSE) continue;

            // true boolean attribute
            if ($value === TRUE) {
                // in XHTML must use unminimized form
                if (Texy::$xhtml) $s .= ' ' . $key . '="' . $key . '"';
                // in HTML should use minimized form
                else $s .= ' ' . $key;
                continue;

            } elseif (is_array($value)) {

                // prepare into temporary array
                $tmp = NULL;
                // use array_change_key_case($value, CASE_LOWER) ?
                foreach ($value as $k => $v) {
                    // skip NULLs & empty string; composite 'style' vs. 'others'
                    if ($v != NULL) $tmp[] = is_string($k) ? $k . ':' . $v : $v;
                }

                if (!$tmp) continue;
                $value = implode($key === 'style' ? ';' : ' ', $tmp);
            }
            // add new attribute
            $s .= ' ' . $key . '="' . Texy::freezeSpaces(htmlSpecialChars($value)) . '"';
        }

        // finish start tag
        return Texy::$xhtml && $this->_empty ? $s . ' />' : $s . '>';
    }


    /**
     * Returns element's end tag
     * @return string
     */
    public function endTag()
    {
        return $this->_name && !$this->_empty
            ? '</' . $this->_name . '>'
            : '';
    }


    /**
     * Returns element's start tag as Texy mark
     * @return string
     */
    public function startMark($texy)
    {
        $s = $this->startTag();
        return $s === '' ? '' : $texy->mark($s, $this->getContentType());
    }


    /**
     * Returns element's end tag as Texy mark
     * @return string
     */
    public function endMark($texy)
    {
        $s = $this->endTag();
        return $s === '' ? '' : $texy->mark($s, $this->getContentType());
    }


    /**
     * @return int
     */
    public function getContentType()
    {
        if (isset(TexyHtml::$inlineCont[$this->_name])) return Texy::CONTENT_INLINE;
        if (isset(TexyHtml::$inline[$this->_name])) return Texy::CONTENT_NONE;

        return Texy::CONTENT_BLOCK;
    }


}
