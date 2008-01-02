<?php

/**
 * Texy! - web text markup-language (for PHP 4)
 * --------------------------------------------
 *
 * Copyright (c) 2004, 2008 David Grudl aka -dgx- (http://www.dgx.cz)
 *
 * This source file is subject to the GNU GPL license that is bundled
 * with this package in the file license.txt.
 *
 * For more information please see http://texy.info/
 *
 * @copyright  Copyright (c) 2004, 2008 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @link       http://texy.info/
 * @package    Texy
 */



/**
 * DTD descriptor
 *   $dtd[element][0] - allowed attributes (as array keys)
 *   $dtd[element][1] - allowed content for an element (content model) (as array keys)
 *                        - array of allowed elements (as keys)
 *                        - FALSE - empty element
 *                        - 0 - special case for ins & del
 * @var array
 * @see TexyHtmlOutputModule::initDTD()
 * @version $Revision$ $Date$
 */
$GLOBALS['TexyHtml::$dtd'] = NULL;

/** @var bool  use XHTML syntax? */
$GLOBALS['TexyHtml::$xhtml'] = TRUE;

/** @var array  empty elements */
$GLOBALS['TexyHtml::$emptyElements'] = array('img'=>1,'hr'=>1,'br'=>1,'input'=>1,'meta'=>1,'area'=>1,
    'base'=>1,'col'=>1,'link'=>1,'param'=>1,'basefont'=>1,'frame'=>1,'isindex'=>1,'wbr'=>1,'embed'=>1);

/** @var array  %inline; elements; replaced elements + br have value '1' */
$GLOBALS['TexyHtml::$inlineElements'] = array('ins'=>0,'del'=>0,'tt'=>0,'i'=>0,'b'=>0,'big'=>0,'small'=>0,'em'=>0,
    'strong'=>0,'dfn'=>0,'code'=>0,'samp'=>0,'kbd'=>0,'var'=>0,'cite'=>0,'abbr'=>0,'acronym'=>0,
    'sub'=>0,'sup'=>0,'q'=>0,'span'=>0,'bdo'=>0,'a'=>0,'object'=>1,'img'=>1,'br'=>1,'script'=>1,
    'map'=>0,'input'=>1,'select'=>1,'textarea'=>1,'label'=>0,'button'=>1,
    'u'=>0,'s'=>0,'strike'=>0,'font'=>0,'applet'=>1,'basefont'=>0, // transitional
    'embed'=>1,'wbr'=>0,'nobr'=>0,'canvas'=>1, // proprietary
); /* class static property */

/** @var array  elements with optional end tag in HTML */
$GLOBALS['TexyHtml::$optionalEnds'] = array('body'=>1,'head'=>1,'html'=>1,'colgroup'=>1,'dd'=>1,
    'dt'=>1,'li'=>1,'option'=>1,'p'=>1,'tbody'=>1,'td'=>1,'tfoot'=>1,'th'=>1,'thead'=>1,'tr'=>1);

/** @see http://www.w3.org/TR/xhtml1/prohibitions.html */
$GLOBALS['TexyHtml::$prohibits'] = array(
    'a' => array('a','button'),
    'img' => array('pre'),
    'object' => array('pre'),
    'big' => array('pre'),
    'small' => array('pre'),
    'sub' => array('pre'),
    'sup' => array('pre'),
    'input' => array('button'),
    'select' => array('button'),
    'textarea' => array('button'),
    'label' => array('button', 'label'),
    'button' => array('button'),
    'form' => array('button', 'form'),
    'fieldset' => array('button'),
    'iframe' => array('button'),
    'isindex' => array('button'),
); /* class private static property */



/**
 * HTML helper
 *
 * usage:
 *       $anchor = TexyHtml::el('a')->href($link)->setText('Texy');
 *       $el->attrs['class'] = 'myclass';
 *
 *       echo $el->startTag(), $el->endTag();
 *
 * @property mixed element's attributes
 * @package Texy
 */
class TexyHtml extends NObject4 /*implements ArrayAccess, Countable*/
{
    /** @var string  element's name */
    var $name;

    /** @var bool  is element empty? */
    var $isEmpty;

    /** @var array  element's attributes */
    var $attrs = array();

    /** @var array  of TexyHtml | string nodes */
    var $children = array();

    /** @var TexyHtml parent element */
    var $parent;


    /**
     * Static factory
     * @param string element name (or NULL)
     * @param array|string element's attributes (or textual content)
     * @return TexyHtml
     */
    function el($name = NULL, $attrs = NULL) /* static */
    {
        $el = new TexyHtml;
        $el->setName($name);
        if (is_array($attrs)) {
            $el->attrs = $attrs;
        } elseif ($attrs !== NULL) {
            $el->setText($attrs);
        }
        return $el;
    }



    /**
     * Changes element's name
     * @param string
     * @param bool  Is element empty?
     * @throws TexyException
     * @return TexyHtml  provides a fluent interface
     */
    function setName($name, $empty = NULL)
    {
        if ($name !== NULL && !is_string($name)) {
            throw (new TexyException('Name must be string or NULL'));
        }

        $this->name = $name;
        $this->isEmpty = $empty === NULL ? isset($GLOBALS['TexyHtml::$emptyElements'][$name]) : (bool) $empty;
        return $this;
    }



    /**
     * Returns element's name
     * @return string
     */
    function getName()
    {
        return $this->name;
    }



    /**
     * Is element empty?
     * @return bool
     */
    function isEmpty()
    {
        return $this->isEmpty;
    }



    /**
     * Special setter for element's attribute
     * @param string path
     * @param array query
     * @return TexyHtml  provides a fluent interface
     */
    function href($path, $params = NULL)
    {
        if ($params) {
        	// missing http_build_query in PHP5
            //$query = http_build_query($params, NULL, '&');
            if ($query !== '') $path .= '?' . $query;
        }
        $this->attrs['href'] = $path;
        return $this;
    }



    /**
     * Sets element's textual content
     * @param string
     * @return TexyHtml  provides a fluent interface
     */
    function setText($text)
    {
        if (is_scalar($text)) {
            $this->children = array($text);
        } elseif ($text !== NULL) {
            throw (new TexyException('Content must be scalar'));
        }
        return $this;
    }



    /**
     * Gets element's textual content
     * @return string
     */
    function getText()
    {
        $s = '';
        foreach ($this->children as $child) {
            if (is_object($child)) return FALSE;
            $s .= $child;
        }
        return $s;
    }



    /**
     * Adds new element's child
     * @param TexyHtml|string child node
     * @return TexyHtml  provides a fluent interface
     */
    function add($child)
    {
        return $this->insert(NULL, $child);
    }



    /**
     * Creates and adds a new TexyHtml child
     * @param string  elements's name
     * @param array|string element's attributes (or textual content)
     * @return TexyHtml  created element
     */
    function create($name, $attrs = NULL)
    {
        $this->insert(NULL, $child = TexyHtml::el($name, $attrs));
        return $child;
    }



    /**
     * Inserts child node
     * @param int
     * @param TexyHtml node
     * @param bool
     * @return TexyHtml  provides a fluent interface
     * @throws TexyException
     */
    function insert($index, $child, $replace = FALSE)
    {
        if (is_a($child, 'TexyHtml')) {
            if ($child->parent !== NULL) {
                throw (new TexyException('Child node already has parent'));
            }
            $child->parent = $this;

        } elseif (!is_string($child)) {
            throw (new TexyException('Child node must be scalar or TexyHtml object'));
        }

        if ($index === NULL)  { // append
            $this->children[] = $child;

        } else { // insert or replace
            array_splice($this->children, (int) $index, $replace ? 1 : 0, array($child));
        }

        return $this;
    }



    /**
     * Inserts (replaces) child node (ArrayAccess implementation)
     * @param int
     * @param TexyHtml node
     * @return void
     */
    function offsetSet($index, $child)
    {
        $this->insert($index, $child, TRUE);
    }



    /**
     * Returns child node (ArrayAccess implementation)
     * @param int index
     * @return mixed
     */
    function offsetGet($index)
    {
        return $this->children[$index];
    }



    /**
     * Exists child node? (ArrayAccess implementation)
     * @param int index
     * @return bool
     */
    function offsetExists($index)
    {
        return isset($this->children[$index]);
    }



    /**
     * Removes child node (ArrayAccess implementation)
     * @param int index
     * @return void
     */
    function offsetUnset($index)
    {
        if (isset($this->children[$index])) {
            $child = $this->children[$index];
            array_splice($this->children, (int) $index, 1);
            $child->parent = NULL;
        }
    }



    /**
     * Required by the Countable interface
     * @return int
     */
    function count()
    {
        return count($this->children);
    }



    /**
     * Required by the IteratorAggregate interface
     * @return ArrayIterator
     */
    function getIterator()
    {
        return new ArrayIterator($this->children);
    }



    /**
     * Returns all of children
     * return array
     */
    function getChildren()
    {
        return $this->children;
    }



    /**
     * Returns parent node
     * @return TexyHtml
     */
    function getParent()
    {
        return $this->parent;
    }



    /**
     * Renders element's start tag, content and end tag to internal string representation
     * @param Texy
     * @return string
     */
    function toString($texy)
    {
        $ct = $this->getContentType();
        $s = $texy->protect($this->startTag(), $ct);

        // empty elements are finished now
        if ($this->isEmpty) {
            return $s;
        }

        // add content
        foreach ($this->children as $child) {
            if (is_object($child)) {
                $s .= $child->toString($texy);
            } else {
                $s .= $child;
            }
        }

        // add end tag
        return $s . $texy->protect($this->endTag(), $ct);
    }



    /**
     * Renders to final HTML
     * @param Texy
     * @return string
     */
    function toHtml($texy)
    {
        return $texy->stringToHtml($this->toString($texy));
    }



    /**
     * Renders to final text
     * @param Texy
     * @return string
     */
    function toText($texy)
    {
        return $texy->stringToText($this->toString($texy));
    }



    /**
     * Returns element's start tag
     * @return string
     */
    function startTag()
    {
        if (!$this->name) {
            return '';
        }

        $s = '<' . $this->name;

        if (is_array($this->attrs)) {
            foreach ($this->attrs as $key => $value)
            {
                // skip NULLs and false boolean attributes
                if ($value === NULL || $value === FALSE) continue;

                // true boolean attribute
                if ($value === TRUE) {
                    // in XHTML must use unminimized form
                    if ($GLOBALS['TexyHtml::$xhtml']) $s .= ' ' . $key . '="' . $key . '"';
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
        }

        // finish start tag
        if ($GLOBALS['TexyHtml::$xhtml'] && $this->isEmpty) {
            return $s . ' />';
        }
        return $s . '>';
    }



    /**
     * Returns element's end tag
     * @return string
     */
    function endTag()
    {
        if ($this->name && !$this->isEmpty) {
            return '</' . $this->name . '>';
        }
        return '';
    }



    /**
     * Clones all children too
     */
    function __clone()
    {
        $this->parent = NULL;
        foreach ($this->children as $key => $value) {
            if (is_object($value)) {
                $this->children[$key] = clone ($value);
            }
        }
    }



    /**
     * @return int
     */
    function getContentType()
    {
        if (!isset($GLOBALS['TexyHtml::$inlineElements'][$this->name])) return TEXY_CONTENT_BLOCK;

        return $GLOBALS['TexyHtml::$inlineElements'][$this->name] ? TEXY_CONTENT_REPLACED : TEXY_CONTENT_MARKUP;
    }



    /**
     * @return void
     */
    function validateAttrs()
    {
        if (isset($GLOBALS['TexyHtml::$dtd'][$this->name])) {
            $dtd = $GLOBALS['TexyHtml::$dtd'][$this->name][0];
            if (is_array($dtd)) {
                foreach ($this->attrs as $attr => $foo) {
                    if (!isset($dtd[$attr])) unset($this->attrs[$attr]);
                }
            }
        }
    }



    function validateChild($child)
    {
        if (isset($GLOBALS['TexyHtml::$dtd'][$this->name])) {
            if (is_object($child)) $child = $child->name;
            return isset($GLOBALS['TexyHtml::$dtd'][$this->name][1][$child]);
        } else {
            return TRUE; // unknown element
        }
    }




    /**
     * Parses text as single line
     * @param Texy
     * @param string
     * @return void
     */
    function parseLine($texy, $s)
    {
        // TODO!
        // special escape sequences
        $s = str_replace(array('\)', '\*'), array('&#x29;', '&#x2A;'), $s);

        $parser = new TexyLineParser($texy, $this);
        $parser->parse($s);
        return $parser;
    }



    /**
     * Parses text as block
     * @param Texy
     * @param string
     * @param bool
     * @return void
     */
    function parseBlock($texy, $s, $indented = FALSE)
    {
        $parser = new TexyBlockParser($texy, $this, $indented);
        $parser->parse($s);
    }



    /**
     * Initializes self::$dtd array
     * @param bool
     * @return void
     */
    function initDTD($strict)
    {
        static $last;
        if ($last === $strict) return;
        $last = $strict;

        require __FILE__ . '/../TexyHtml.DTD.php';
    }

}
