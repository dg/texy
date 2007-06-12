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
if (!class_exists('Texy')) die();




define('TexyModifier_HALIGN_LEFT',     'left'); /* class constant */
define('TexyModifier_HALIGN_RIGHT',    'right'); /* class constant */
define('TexyModifier_HALIGN_CENTER',   'center'); /* class constant */
define('TexyModifier_HALIGN_JUSTIFY',  'justify'); /* class constant */
define('TexyModifier_VALIGN_TOP',      'top'); /* class constant */
define('TexyModifier_VALIGN_MIDDLE',   'middle'); /* class constant */
define('TexyModifier_VALIGN_BOTTOM',   'bottom'); /* class constant */


/** @var array  list of properties which are regarded as HTML element attributes */
$GLOBALS['TexyModifier::$elAttrs'] = array(
    'abbr'=>1,'accesskey'=>1,'align'=>1,'alt'=>1,'archive'=>1,'axis'=>1,'bgcolor'=>1,'cellpadding'=>1,
    'cellspacing'=>1,'char'=>1,'charoff'=>1,'charset'=>1,'cite'=>1,'classid'=>1,'codebase'=>1,'codetype'=>1,
    'colspan'=>1,'compact'=>1,'coords'=>1,'data'=>1,'datetime'=>1,'declare'=>1,'dir'=>1,'face'=>1,'frame'=>1,
    'headers'=>1,'href'=>1,'hreflang'=>1,'hspace'=>1,'ismap'=>1,'lang'=>1,'longdesc'=>1,'name'=>1,
    'noshade'=>1,'nowrap'=>1,'onblur'=>1,'onclick'=>1,'ondblclick'=>1,'onkeydown'=>1,'onkeypress'=>1,
    'onkeyup'=>1,'onmousedown'=>1,'onmousemove'=>1,'onmouseout'=>1,'onmouseover'=>1,'onmouseup'=>1,'rel'=>1,
    'rev'=>1,'rowspan'=>1,'rules'=>1,'scope'=>1,'shape'=>1,'size'=>1,'span'=>1,'src'=>1,'standby'=>1,
    'start'=>1,'summary'=>1,'tabindex'=>1,'target'=>1,'title'=>1,'type'=>1,'usemap'=>1,'valign'=>1,
    'value'=>1,'vspace'=>1,
); /* class static property */


/**
 * Modifier processor
 *
 * Modifiers are texts like .(title)[class1 class2 #id]{color: red}>^
 *   .         starts with dot
 *   (...)     title or alt modifier
 *   [...]     classes or ID modifier
 *   {...}     inner style modifier
 *   < > <> =  horizontal align modifier
 *   ^ - _     vertical align modifier
 */
class TexyModifier
{
    // TODO!
    /** @var var */
    var $empty = TRUE;

    /** @var string */
    var $id;

    /** @var array */
    var $classes = array();

    /** @var array */
    var $styles = array();

    /** @var array */
    var $attrs = array();

    /** @var string */
    var $hAlign;

    /** @var string */
    var $vAlign;

    /** @var string */
    var $title;

    /** @var string */
    var $cite;

    /**
     * @param string modifier to parse
     */
    function __construct($mod=NULL)
    {
        $this->setProperties($mod);
    }


    function setProperties($mod)
    {
        if (!$mod) return;
        $this->empty = FALSE;

        $p = 0;
        $len = strlen($mod);

        while ($p < $len)
        {
            $ch = $mod[$p];

            if ($ch === '(') { // title
                $a = strpos($mod, ')', $p) + 1;
                $this->title = Texy::unescapeHtml(trim(substr($mod, $p + 1, $a - $p - 2)));
                $p = $a;

            } elseif ($ch === '{') { // style & attributes
                $a = strpos($mod, '}', $p) + 1;
                foreach (explode(';', substr($mod, $p + 1, $a - $p - 2)) as $value) {
                    $pair = explode(':', $value, 2);
                    $prop = strtolower(trim($pair[0]));
                    if ($prop === '' || !isset($pair[1])) continue;
                    $value = trim($pair[1]);

                    if (isset($GLOBALS['TexyModifier::$elAttrs'][$prop])) // attribute
                        $this->attrs[$prop] = $value;
                    elseif ($value !== '')  // style
                        $this->styles[$prop] = $value;
                }
                $p = $a;

            } elseif ($ch === '[') { // classes & ID
                $a = strpos($mod, ']', $p) + 1;
                $s = str_replace('#', ' #', substr($mod, $p + 1, $a - $p - 2));
                foreach (explode(' ', $s) as $value) {
                    if ($value === '') continue;

                    if ($value{0} === '#')
                        $this->id = substr($value, 1);
                    else
                        $this->classes[] = $value;
                }
                $p = $a;
            }
            // alignment
            elseif ($ch === '^') { $this->vAlign = TexyModifier_VALIGN_TOP; $p++; }
            elseif ($ch === '-') { $this->vAlign = TexyModifier_VALIGN_MIDDLE; $p++; }
            elseif ($ch === '_') { $this->vAlign = TexyModifier_VALIGN_BOTTOM; $p++; }
            elseif ($ch === '=') { $this->hAlign = TexyModifier_HALIGN_JUSTIFY; $p++; }
            elseif ($ch === '>') { $this->hAlign = TexyModifier_HALIGN_RIGHT; $p++; }
            elseif (substr($mod, $p, 2) === '<>') { $this->hAlign = TexyModifier_HALIGN_CENTER; $p+=2; }
            elseif ($ch === '<') { $this->hAlign = TexyModifier_HALIGN_LEFT; $p++; }
            else { break; }
        }
    }



    /**
     * Decorates TexyHtml element
     * @param Texy   base Texy object
     * @param TexyHtml  element to decorate
     * @return void
     */
    function decorate($texy, $el)
    {
        $elAttrs = & $el->attrs;

        // tag & attibutes
        $tmp = $texy->allowedTags; // speed-up
        if (!$this->attrs) {

        } elseif ($tmp === TEXY_ALL) {
            $elAttrs = $this->attrs;

        } elseif (is_array($tmp) && isset($tmp[$el->getName()])) {
            $tmp = $tmp[$el->getName()];

            if ($tmp === TEXY_ALL) {
                $elAttrs = $this->attrs;

            } elseif (is_array($tmp) && count($tmp)) {
                $tmp = array_flip($tmp);
                foreach ($this->attrs as $key => $val)
                    if (isset($tmp[$key])) $el->attrs[$key] = $val;
            }
        }

        // title
        if ($this->title !== NULL)
            $elAttrs['title'] = $texy->typographyModule->postLine($this->title);

        // classes & ID
        if ($this->classes || $this->id !== NULL) {
            $tmp = $texy->_classes; // speed-up
            if ($tmp === TEXY_ALL) {
                foreach ($this->classes as $val) $elAttrs['class'][] = $val;
                $elAttrs['id'] = $this->id;
            } elseif (is_array($tmp)) {
                foreach ($this->classes as $val)
                    if (isset($tmp[$val])) $elAttrs['class'][] = $val;

                if (isset($tmp['#' . $this->id])) $elAttrs['id'] = $this->id;
            }
        }

        // styles
        if ($this->styles) {
            $tmp = $texy->_styles;  // speed-up
            if ($tmp === TEXY_ALL) {
                foreach ($this->styles as $prop => $val) $elAttrs['style'][$prop] = $val;
            } elseif (is_array($tmp)) {
                foreach ($this->styles as $prop => $val)
                    if (isset($tmp[$prop])) $elAttrs['style'][$prop] = $val;
            }
        }

        // align
        if ($this->hAlign) $elAttrs['style']['text-align'] = $this->hAlign;
        if ($this->vAlign) $elAttrs['style']['vertical-align'] = $this->vAlign;

        return $el;
    }





    function TexyModifier()  /* PHP 4 constructor */
    {
        // generate references (see http://www.dgx.cz/trine/item/how-to-emulate-php5-object-model-in-php4)
        foreach ($this as $key => $foo) $GLOBALS['$$HIDDEN$$'][] = & $this->$key;

        // call php5 constructor
        $args = func_get_args();
        call_user_func_array(array(&$this, '__construct'), $args);
    }
} // TexyModifier
