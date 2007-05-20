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
if (!class_exists('Texy', FALSE)) die();



/**
 * HTML helper
 *
 * usage:
 *       $anchor = TexyHtml::el('a')->href($link)->setText('Texy');
 *       $el['href'] = $link;
 *
 *       echo $el->startTag(), $el->endTag();
 *
 */
class TexyHtml implements ArrayAccess
{
    /** @var string  element's name */
    public $name;

    /** @var array  element's attributes */
    public $attrs = array();

    /**
     * @var mixed  element's content
     *   array - child nodes
     *   string - content as string (text-node)
     */
    public $children;

    /** @var bool  is element empty? */
    public $isEmpty;

    /** @var bool  use XHTML syntax? */
    static public $xhtml = TRUE;

    /** @var array  replaced elements + br */
    static private $replacedTags = array('br'=>1,'button'=>1,'iframe'=>1,'img'=>1,'input'=>1,
        'object'=>1,'script'=>1,'select'=>1,'textarea'=>1,'applet'=>1,'embed'=>1,'canvas'=>1);

    /** @var array  empty elements */
    static public $emptyTags = array('img'=>1,'hr'=>1,'br'=>1,'input'=>1,'meta'=>1,'area'=>1,
        'base'=>1,'col'=>1,'link'=>1,'param'=>1,'basefont'=>1,'frame'=>1,'isindex'=>1,'wbr'=>1,'embed'=>1);



    /**
     * @param string element name
     * @param array element's attributes
     */
    public function __construct($name=NULL, $attrs=NULL)
    {
        if ($name !== NULL) $this->setName($name);
        if ($attrs !== NULL) {
            if (!is_array($attrs))
                throw new Exception('Attributes must be array');

            $this->attrs = $attrs;
        }
        return $this;
    }


    /**
     * Static factory
     * @param string element name (or NULL)
     * @param array element's attributes
     * @return TexyHtml
     */
    static public function el($name=NULL, $attrs=NULL)
    {
        return new self($name, $attrs);
    }


    /**
     * Changes element's name
     * @param string
     * @return TexyHtml  itself
     */
    public function setName($name)
    {
        if ($name !== NULL && !is_string($name))
            throw new Exception('Name must be string or NULL');

        $this->name = $name;
        $this->isEmpty = isset(self::$emptyTags[$name]);
        return $this;
    }


    /**
     * Sets element's textual content
     * @param string
     * @return TexyHtml  itself
     */
    public function setText($text)
    {
        if ($text === NULL)
            $text = '';
        elseif (!is_scalar($text))
            throw new Exception('Content must be scalar');

        $this->children = $text;
        return $this;
    }



    /**
     * Gets element's textual content
     * @return string
     */
    public function getText()
    {
        if (is_array($this->children)) return FALSE;

        return $this->children;
    }



    /**
     * Adds new element's child
     * @param string|TexyHtml object
     * @return TexyHtml  itself
     */
    public function addChild($child)
    {
        $this->children[] = $child;
        return $this;
    }


    /**
     * Returns child node
     * @param mixed index
     * @return TexyHtml|string
     */
    public function getChild($index)
    {
        if (isset($this->children[$index]))
            return $this->children[$index];

        return NULL;
    }


    /**
     * Adds and creates new TexyHtml child
     * @param string|TexyHtml  elements's name or TexyHtml object
     * @return TexyHtml
     */
    public function add($child)
    {
        if (!($child instanceof self))
            $child = new self($child);

        return $this->children[] = $child;
    }


    /**
     * Overloaded setter for element's attribute
     * @param string attribute name
     * @param array value
     * @return TexyHtml  itself
     */
/*
    public function __call($m, $args)
    {
        $this->attrs[$m] = $args[0];
        return $this;
    }
*/


    /** these are the required ArrayAccess functions */
    /**
     * Getter for element's attribute
     * @param string attribute name
     * @return mixed
     */
    public function offsetGet($i)
    {
        if (isset($this->attrs[$i])) {
            if (is_array($this->attrs[$i]))
                $this->attrs[$i] = new ArrayObject($this->attrs[$i]);

            return $this->attrs[$i];
        }

        // special cases
        if ($i === 'style' || $i === 'class') {
            return $this->attrs[$i] = new ArrayObject;
        }

        return NULL;
    }

    /**
     * Setter for element's attribute
     * @param string attribute name
     * @param mixed value
     * @return void
     */
    public function offsetSet($i, $value)
    {
        if ($i === NULL) throw new Exception('Invalid TexyHtml usage.');
        $this->attrs[$i] = $value;
    }

    /**
     * Exists element's attribute?
     * @param string attribute name
     * @return bool
     */
    public function offsetExists($i)
    {
        return isset($this->attrs[$i]);
    }

    /**
     * Unsets element's attribute
     * @param string attribute name
     * @return void
     */
    public function offsetUnset($i)
    {
        unset($this->attrs[$i]);
    }
    /** end required ArrayAccess functions */


    /**
     * Renders element's start tag, content and end tag
     * @return string
     */
    public function export($texy)
    {
        $ct = $this->getContentType();
        $s = $texy->protect($this->startTag(), $ct);

        // empty elements are finished now
        if ($this->isEmpty) return $s;

        // add content
        if (is_array($this->children)) {
            foreach ($this->children as $val) {
                if ($val instanceof self)
                    $s .= $val->export($texy);
                else
                    $s .= $val;
            }
        } else {
            $s .= $this->children;
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
        if (!$this->name) return '';

        $s = '<' . $this->name;

        if (is_array($this->attrs))
        foreach ($this->attrs as $key => $value)
        {
            // skip NULLs and false boolean attributes
            if ($value === NULL || $value === FALSE) continue;

            // true boolean attribute
            if ($value === TRUE) {
                // in XHTML must use unminimized form
                if (self::$xhtml) $s .= ' ' . $key . '="' . $key . '"';
                // in HTML should use minimized form
                else $s .= ' ' . $key;
                continue;

            } elseif (is_array($value) || is_object($value)) {

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
        if (self::$xhtml && $this->isEmpty) return $s . ' />';
        return $s . '>';
    }


    /**
     * Returns element's end tag
     * @return string
     */
    public function endTag()
    {
        if ($this->name && !$this->isEmpty)
            return '</' . $this->name . '>';
        return '';
    }


    /**
     * Is element textual node?
     * @return bool
     */
    public function isTextual()
    {
        return !$this->isEmpty && is_scalar($this->children);
    }


    /**
     * Clones all children too
     */
    public function __clone()
    {
        if (is_array($this->children)) {
            foreach ($this->children as $key => $val)
                if ($val instanceof self)
                    $this->children[$key] = clone $val;
        }
    }


    /**
     * @return int
     */
    public function getContentType()
    {
        if (isset(self::$replacedTags[$this->name])) return Texy::CONTENT_REPLACED;
        if (isset(TexyHtmlCleaner::$inline[$this->name])) return Texy::CONTENT_MARKUP;

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
        // TODO!
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


    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }

}
