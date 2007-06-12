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
 *       $el->class = 'myclass';
 *
 *       echo $el->startTag(), $el->endTag();
 *
 */
class TexyHtml implements ArrayAccess // TODO: use ArrayAccess for children
{
    /** @var string  element's name */
    private $name;

    /** @var bool  is element empty? */
    private $isEmpty;

    /** @var array  element's attributes */
    public $attrs = array();

    /**
     * @var mixed  element's content
     *   array of TexyHtml - child nodes
     *   string - content as string (text-node)
     */
    public $children;

    /** @var bool  use XHTML syntax? */
    static public $xhtml = TRUE;

    /** @var array  replaced elements + br */
    static private $replacedTags = array('br'=>1,'button'=>1,'iframe'=>1,'img'=>1,'input'=>1,
        'object'=>1,'script'=>1,'select'=>1,'textarea'=>1,'applet'=>1,'embed'=>1,'canvas'=>1);

    /** @var array  empty elements */
    static public $emptyTags = array('img'=>1,'hr'=>1,'br'=>1,'input'=>1,'meta'=>1,'area'=>1,
        'base'=>1,'col'=>1,'link'=>1,'param'=>1,'basefont'=>1,'frame'=>1,'isindex'=>1,'wbr'=>1,'embed'=>1);



    /**
     * Static factory
     * @param string element name (or NULL)
     * @param array element's attributes
     * @return TexyHtml
     */
    static public function el($name=NULL, $attrs=NULL)
    {
        $el = new self;

        if ($name !== NULL)
            $el->setName($name);

        if ($attrs !== NULL) {
            if (!is_array($attrs))
                throw new Exception('Attributes must be array');

            $el->attrs = $attrs;
        }

        return $el;
    }


    /**
     * Static factory for textual element
     * @param string
     * @return TexyHtml
     */
    static public function text($text)
    {
        $el = new self;
        $el->setText($text);
        return $el;
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
     * Returns element's name
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * Is element empty?
     * @param optional setter
     * @return bool
     */
    public function isEmpty($val=NULL)
    {
        if (is_bool($val)) $this->isEmpty = $val;
        return $this->isEmpty;
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
     * @param TexyHtml object
     * @return TexyHtml  itself
     */
    public function addChild(TexyHtml $child)
    {
        $this->children[] = $child;
        return $this;
    }


    /**
     * Returns child node
     * @param mixed index
     * @return TexyHtml
     */
    public function getChild($index)
    {
        if (isset($this->children[$index]))
            return $this->children[$index];

        return NULL;
    }


    /**
     * Adds and creates new TexyHtml child
     * @param string  elements's name
     * @param string optional textual content
     * @return TexyHtml
     */
    public function add($name, $text=NULL)
    {
        $child = new self;
        $child->setName($name);
        if ($text !== NULL) $child->setText($text);
        return $this->children[] = $child;
    }


    /**
     * Overloaded setter for element's attribute
     * @param string    property name
     * @param mixed     property value
     * @return void
     */
    public function __set($nm, $val)
    {
        $this->attrs[$nm] = $val;
    }


    /**
     * Overloaded getter for element's attribute
     * @param string    property name
     * @return mixed    property value
     */
    public function &__get($nm)
    {
        return $this->attrs[$nm];
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


    /**
     * Special setter for element's attribute
     * @param string path
     * @param array query
     * @return TexyHtml  itself
     */
    public function href($path, $params=NULL)
    {
        if ($params) {
            $query = http_build_query($params, NULL, '&');
            if ($query !== '') $path .= '?' . $query;
        }
        $this->attrs['href'] = $path;
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
        if ($this->isEmpty) return $s;

        // add content
        if (is_array($this->children)) {
            foreach ($this->children as $val)
                $s .= $val->export($texy);

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
     * @param bool
     * @return void
     */
    public function parseBlock($texy, $s, $topLevel=FALSE)
    {
        $parser = new TexyBlockParser($texy, $this);
        $parser->topLevel = $topLevel;
        $parser->parse($s);
    }




    // TODO: REMOVE
    public function offsetGet($i)
    {
        trigger_error('Manipulace s atributy pres $el[\'attr\']=VALUE je od revize 133 zrusena', E_USER_WARNING);
    }

    public function offsetSet($i, $value) { $this->offsetGet(NULL); }
    public function offsetExists($i) { $this->offsetGet(NULL); }
    public function offsetUnset($i) { $this->offsetGet(NULL); }
}
