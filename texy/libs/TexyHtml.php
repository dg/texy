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
 * HTML helper
 */
class TexyHtml
{
    /** @var string  element's name */
    public $elName;

    /** @var array|FALSE  element's content, FALSE means empty element */
    public $childNodes = array();

    /** @var mixed  user data */
    public $eXtra;

    /* element's attributes are not explicitly declared */


    /**
     * TexyHtml element's factory
     * @param string element name (or NULL)
     * @return TexyHtml
     */
    static public function el($name=NULL)
    {
        $el = new self();
        $el->elName = $name;
        if (isset(Texy::$emptyTags[$name])) $el->childNodes = FALSE;
        return $el;
    }


    /**
     * Changes element's name
     * @param string
     * @return TexyHtml  itself
     */
    public function setElement($name)
    {
        $this->elName = $name;
        if (isset(Texy::$emptyTags[$name])) $el->childNodes = FALSE;
        return $this;
    }


    /**
     * Sets element's attributes
     * @param array
     * @return TexyHtml  itself
     */
    public function setAttrs($attrs)
    {
        foreach ($attrs as $key => $value) $el->$key = $value;
        return $this;
    }


    /**
     * Sets element's content
     * @param string|TexyHtml object
     * @return TexyHtml  itself
     */
    public function setContent($content)
    {
        $this->childNodes = array( $content );
        return $this;
    }


    /**
     * Gets element's content
     * @return string
     */
    public function getContent()
    {
        if (isset($this->childNodes[0])) return $this->childNodes[0];
        return NULL;
    }


    /**
     * Overloaded setter for element's attribute
     * @param string function name
     * @param array function arguments
     * @return TexyHtml  itself
     */
    public function __call($m, $args)
    {
        $this->$m = $args[0];
        return $this;
    }


    /**
     * Renders element's start tag, content and end tag
     * @return string
     */
    public function export($texy)
    {
        $ct = $this->getContentType();
        $s = $texy->protect($this->startTag(), $ct);

        // empty elements are finished now
        if ($this->childNodes === FALSE) return $s;

        // add content
        if (!is_array($this->childNodes))
            throw new Exception('TexyHtml::childNodes bad usage.');

        foreach ($this->childNodes as $val)
            if ($val instanceof self) $s .= $val->export($texy);
            else $s .= $val;

        $s .= $texy->protect($this->endTag(), $ct);

        return $s;
    }


    /**
     * Returns element's start tag
     * @return string
     */
    public function startTag()
    {
        if (!$this->elName) return '';

        $s = '<' . $this->elName;

        // for each attribute...
        $attrs = (array) $this;
        unset($attrs['elName'], $attrs['childNodes'], $attrs['eXtra']);

        foreach ($attrs as $key => $value)
        {
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
                foreach ($value as $k => $v) {
                    // skip NULLs & empty string; composite 'style' vs. 'others'
                    if ($v == NULL) continue;

                    if (is_string($k)) $tmp[] = $k . ':' . $v;
                    else $tmp[] = $v;
                }

                if (!$tmp) continue;
                $value = implode($key === 'style' ? ';' : ' ', $tmp);
            }
            // add new attribute
            $value = str_replace(array('&', '"', '<', '>', '@'), array('&amp;', '&quot;', '&lt;', '&gt;', '&#64;'), $value);
            $s .= ' ' . $key . '="' . Texy::freezeSpaces($value) . '"';
        }

        // finish start tag
        if (Texy::$xhtml  && $this->childNodes === FALSE) return $s . ' />';
        return $s . '>';
    }


    /**
     * Returns element's end tag
     * @return string
     */
    public function endTag()
    {
        if ($this->elName && $this->childNodes !== FALSE)
            return '</' . $this->elName . '>';
        return '';
    }



    /**
     * @return int
     */
    public function getContentType()
    {
        if (isset(Texy::$inlineCont[$this->elName])) return Texy::CONTENT_INLINE;
        if (isset(Texy::$inlineTags[$this->elName])) return Texy::CONTENT_NONE;

        return Texy::CONTENT_BLOCK;
    }



    /**
     * Parses text as block
     * @param Texy
     * @param string
     * @return void
     */
    public function parseLine($texy, $s)
    {
        $parser = new TexyLineParser($texy);
        $this->childNodes[] = $parser->parse($s);
    }



    /**
     * Parses text as single line
     * @param Texy
     * @param string
     * @return void
     */
    public function parseBlock($texy, $s)
    {
        $parser = new TexyBlockParser($texy);
        $this->childNodes = $parser->parse($s);
    }
}
