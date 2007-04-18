<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * Copyright (c) 2004-2007 David Grudl aka -dgx- <dave@dgx.cz>
 *
 * @version  $Revision$ $Date$
 * @package  Texy
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

    /**
     * @var mixed  element's content
     *   array - child nodes
     *   string - content as string (text-node)
     *   FALSE - element is empty
     */
    public $childNodes;

    /** @var mixed  user data */
    public $userData;

    /** @var bool  use XHTML syntax? */
    static public $XHTML = TRUE;

    /** @var array  replaced elements + br */
    static private $replacedTags = array('br'=>1,'button'=>1,'iframe'=>1,'img'=>1,'input'=>1,
        'object'=>1,'script'=>1,'select'=>1,'textarea'=>1,'applet'=>1,'embed'=>1,'canvas'=>1);

    /** @var array  empty elements */
    static public $emptyTags = array('img'=>1,'hr'=>1,'br'=>1,'input'=>1,'meta'=>1,'area'=>1,
        'base'=>1,'col'=>1,'link'=>1,'param'=>1,'basefont'=>1,'frame'=>1,'isindex'=>1,'wbr'=>1,'embed'=>1);

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
        if (isset(self::$emptyTags[$name])) $el->childNodes = FALSE;
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
        if (isset(self::$emptyTags[$name])) $this->childNodes = FALSE;
        return $this;
    }


    /**
     * Sets element's attributes
     * @param array
     * @return TexyHtml  itself
     */
    public function setAttrs($attrs)
    {
        foreach ($attrs as $key => $value) $this->$key = $value;
        return $this;
    }


    /**
     * Sets element's content
     * @param string object
     * @return TexyHtml  itself
     */
    public function setContent($content)
    {
        if (!is_scalar($content))
            throw new Exception('Content must be scalar');

        $this->childNodes = $content;
        return $this;
    }


    /**
     * Gets element's content
     * @return string
     */
    public function getContent()
    {
        if (is_array($this->childNodes)) return NULL;
        return $this->childNodes;
    }


    /**
     * Adds new child of element's content
     * @param string|TexyHtml object
     * @return TexyHtml  itself
     */
    public function addChild($content)
    {
        $this->childNodes[] = $content;
        return $this;
    }


    /**
     * Overloaded setter for element's attribute
     * @param string function name
     * @param array function arguments
     * @return TexyHtml  itself
     */
     /*
    public function __call($m, $args)
    {
        $this->$m = $args[0];
        return $this;
    }
    */


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
        if (is_array($this->childNodes)) {
            foreach ($this->childNodes as $val) {
                if ($val instanceof self)
                    $s .= $val->export($texy);
                else
                    $s .= $val;
            }
        } else {
            $s .= $this->childNodes;
        }

        // add end tag
        return $s . $texy->protect($this->endTag(), $ct);
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
        unset($attrs['elName'], $attrs['childNodes'], $attrs['userData']);

        foreach ($attrs as $key => $value)
        {
            // skip NULLs and false boolean attributes
            if ($value === NULL || $value === FALSE) continue;

            // true boolean attribute
            if ($value === TRUE) {
                // in XHTML must use unminimized form
                if (self::$XHTML) $s .= ' ' . $key . '="' . $key . '"';
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

            } elseif ($key === 'href' && substr($value, 0, 7) === 'mailto:') {
                // email-obfuscate hack
                $tmp = '';
                for ($i=0; $i<strlen($value); $i++) $tmp .= '&#' . ord($value[$i]) . ';'; // WARNING: no utf support
                $s .= ' href="' . $tmp . '"';
                continue;
            }

            // add new attribute
            $value = str_replace(array('&', '"', '<', '>'), array('&amp;', '&quot;', '&lt;', '&gt;'), $value);
            $s .= ' ' . $key . '="' . Texy::freezeSpaces($value) . '"';
        }

        // finish start tag
        if (self::$XHTML && $this->childNodes === FALSE) return $s . ' />';
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
     * Is element empty?
     * @return bool
     */
    public function isEmpty()
    {
        return $this->childNodes === FALSE;
    }


    /**
     * Is element textual node?
     * @return bool
     */
    public function isTextual()
    {
        return $this->childNodes !== FALSE && is_scalar($this->childNodes);
    }


    /**
     * Clones all childnodes too
     */
    public function __clone()
    {
        if (is_array($this->childNodes)) {
            foreach ($this->childNodes as $key => $val)
                if ($val instanceof self)
                    $this->childNodes[$key] = clone $val;
        }
    }


    /**
     * @return int
     */
    public function getContentType()
    {
        if (isset(self::$replacedTags[$this->elName])) return Texy::CONTENT_REPLACED;
        if (isset(TexyHtmlFormatter::$inline[$this->elName])) return Texy::CONTENT_MARKUP;

        return Texy::CONTENT_BLOCK;
    }



    /**
     * Parses text as single line
     * @param Texy
     * @param string
     * @return void
     */
    public function parseLine($texy, $s)
    {
        // special escape sequences
        $s = str_replace(array('\)', '\*'), array('&#x29;', '&#x2A;'), $s);

        $parser = new TexyLineParser($texy, $this);
        $parser->parse($s);
    }



    /**
     * Parses text as block
     * @param Texy
     * @param string
     * @return void
     */
    public function parseBlock($texy, $s)
    {
        $parser = new TexyBlockParser($texy, $this);
        $parser->parse($s);
    }

}
