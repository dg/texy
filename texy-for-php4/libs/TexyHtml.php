<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004-2007 David Grudl aka -dgx- (http://www.dgx.cz)
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @version    $Revision$ $Date$
 * @category   Text
 * @package    Texy
 */



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

/**
 * DTD descriptor
 *   $dtd[element][0] - allowed attributes (as array keys)
 *   $dtd[element][1] - allowed content for an element (content model) (as array keys)
 *                        - array of allowed elements (as keys)
 *                        - FALSE - empty element
 *                        - 0 - special case for ins & del
 * @var array
 * @see TexyHtmlOutputModule::initDTD()
 */
$GLOBALS['TexyHtml::$dtd'] = NULL;

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
 */
class TexyHtml extends TexyBase
{
    /** @var string  element's name */
    var $name;

    /** @var TexyHtml parent element */
    var $parent;

    /** @var bool  is element empty? */
    var $isEmpty;

    /** @var array  element's attributes */
    var $attrs = array();

    /** @var array  of TexyHtml | string nodes */
    var $children = array();

    /**
     * Static factory
     * @param string element name (or NULL)
     * @param array element's attributes
     * @return TexyHtml
     */
    function el($name = NULL, $attrs = NULL) /* static */
    {
        $el = new TexyHtml;

        if ($name !== NULL) {
            $el->setName($name);
        }

        if ($attrs !== NULL) {
            if (!is_array($attrs)) {
                trigger_error('Attributes must be array.', E_USER_WARNING);
                return FALSE;
            }

            $el->attrs = $attrs;
        }

        return $el;
    }



    /**
     * Changes element's name
     * @param string
     * @return TexyHtml  itself
     */
    function setName($name)
    {
        if ($name !== NULL && !is_string($name)) {
            trigger_error('Name must be string or NULL.', E_USER_WARNING);
            return FALSE;
        }

        $this->name = $name;
        $this->isEmpty = isset($GLOBALS['TexyHtml::$emptyElements'][$name]);
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
     * @param optional setter
     * @return bool
     */
    function isEmpty($value = NULL)
    {
        if (is_bool($value)) {
            $this->isEmpty = $value;
        }

        return $this->isEmpty;
    }



    /**
     * Sets element's textual content
     * @param string
     * @return TexyHtml  itself
     */
    function setText($text)
    {
        if (is_scalar($text)) {
            $this->children = array($text);
        } elseif ($text !== NULL) {
            trigger_error('Content must be scalar.', E_USER_WARNING);
            return FALSE;
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
     * Adds and creates new TexyHtml child
     * @param string  elements's name
     * @param string optional textual content
     * @return TexyHtml
     */
    function add($name, $text = NULL)
    {
        $child = new TexyHtml;
        $child->setName($name);
        if ($text !== NULL) {
            $child->setText($text);
        }
        $this->addChild($child);
        return $child;
    }



    /**
     * Adds new element's child
     * @param TexyHtml|string child node
     * @param mixed index
     * @return TexyHtml  itself
     */
    function addChild($child)
    {
        if (is_a($child, 'TexyHtml')) {
            //$child->parent = $this;
        } elseif (!is_string($child)) {
            trigger_error('Child node must be scalar or TexyHtml object.', E_USER_WARNING);
            return FALSE;
        }

        $this->children[] = $child;
        return $this;
    }



    /**
     * Returns child node
     * @param mixed index
     * @return TexyHtml
     */
    function getChild($index)
    {
        return $this->children[$index];
    }



    /**
     * Overloaded setter for element's attribute
     * @param string    property name
     * @param mixed     property value
     * @return void
     */
    function __set($name, $value)
    {
        // works only in PHP5
        $this->attrs[$name] = $value;
    }



    /**
     * Overloaded getter for element's attribute
     * @param string    property name
     * @return mixed    property value
     */
    function &__get($name)
    {
        // works only in PHP5
        return $this->attrs[$name];
    }



    /**
     * Special setter for element's attribute
     * @param string path
     * @param array query
     * @return TexyHtml  itself
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

                } elseif ($key === 'href' && substr($value, 0, 7) === 'mailto:') {
                    // email-obfuscate hack
                    $tmp = '';
                    for ($i = 0; $i<strlen($value); $i++) $tmp .= '&#' . ord($value[$i]) . ';'; // WARNING: no utf support
                    $s .= ' href="' . $tmp . '"';
                    continue;
                }

                // add new attribute
                $value = str_replace(array('&', '"', '<', '>'), array('&amp;', '&quot;', '&lt;', '&gt;'), $value);
                $s .= ' ' . $key . '="' . Texy::freezeSpaces($value) . '"';
            }
        }

        // finish start tag
        if ($GLOBALS['TexyHtml::$xhtml']&& $this->isEmpty) return $s . ' />';
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
     * Initializes TexyHtml::$dtd array
     * @param bool
     * @return void
     */
    function initDTD($strict)
    {
        TexyHtml_initDTD($strict);
    }

}
