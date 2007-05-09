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
    const HALIGN_LEFT =    'left';
    const HALIGN_RIGHT =   'right';
    const HALIGN_CENTER =  'center';
    const HALIGN_JUSTIFY = 'justify';
    const VALIGN_TOP =     'top';
    const VALIGN_MIDDLE =  'middle';
    const VALIGN_BOTTOM =  'bottom';

    // TODO!
    /** @var var */
    public $empty = TRUE;

    /** @var string */
    public $id;

    /** @var array */
    public $classes = array();

    /** @var array */
    public $styles = array();

    /** @var array */
    public $attrs = array();

    /** @var string */
    public $hAlign;

    /** @var string */
    public $vAlign;

    /** @var string */
    public $title;

    /** @var string */
    public $cite;

    /** @var array  list of properties which are regarded as HTML element attributes */
    static public $elAttrs = array(
        'abbr'=>1,'accesskey'=>1,'align'=>1,'alt'=>1,'archive'=>1,'axis'=>1,'bgcolor'=>1,'cellpadding'=>1,
        'cellspacing'=>1,'char'=>1,'charoff'=>1,'charset'=>1,'cite'=>1,'classid'=>1,'codebase'=>1,'codetype'=>1,
        'colspan'=>1,'compact'=>1,'coords'=>1,'data'=>1,'datetime'=>1,'declare'=>1,'dir'=>1,'face'=>1,'frame'=>1,
        'headers'=>1,'href'=>1,'hreflang'=>1,'hspace'=>1,'ismap'=>1,'lang'=>1,'longdesc'=>1,'name'=>1,
        'noshade'=>1,'nowrap'=>1,'onblur'=>1,'onclick'=>1,'ondblclick'=>1,'onkeydown'=>1,'onkeypress'=>1,
        'onkeyup'=>1,'onmousedown'=>1,'onmousemove'=>1,'onmouseout'=>1,'onmouseover'=>1,'onmouseup'=>1,'rel'=>1,
        'rev'=>1,'rowspan'=>1,'rules'=>1,'scope'=>1,'shape'=>1,'size'=>1,'span'=>1,'src'=>1,'standby'=>1,
        'start'=>1,'summary'=>1,'tabindex'=>1,'target'=>1,'title'=>1,'type'=>1,'usemap'=>1,'valign'=>1,
        'value'=>1,'vspace'=>1,
    );


    /**
     * @param string modifier to parse
     */
    public function __construct($mod=NULL)
    {
        $this->setProperties($mod);
    }


    public function setProperties($mod)
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

                    if (isset(self::$elAttrs[$prop])) // attribute
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
            elseif ($ch === '^') { $this->vAlign = self::VALIGN_TOP; $p++; }
            elseif ($ch === '-') { $this->vAlign = self::VALIGN_MIDDLE; $p++; }
            elseif ($ch === '_') { $this->vAlign = self::VALIGN_BOTTOM; $p++; }
            elseif ($ch === '=') { $this->hAlign = self::HALIGN_JUSTIFY; $p++; }
            elseif ($ch === '>') { $this->hAlign = self::HALIGN_RIGHT; $p++; }
            elseif (substr($mod, $p, 2) === '<>') { $this->hAlign = self::HALIGN_CENTER; $p+=2; }
            elseif ($ch === '<') { $this->hAlign = self::HALIGN_LEFT; $p++; }
            else { break; }
        }
    }



    /**
     * Decorates TexyHtml element
     * @param Texy   base Texy object
     * @param TexyHtml  element to decorate
     * @return void
     */
    public function decorate($texy, $el)
    {
        // tag & attibutes
        $tmp = $texy->allowedTags; // speed-up
        if (!$this->attrs) {

        } elseif ($tmp === Texy::ALL) {
            $el->setAttrs($this->attrs);

        } elseif (is_array($tmp) && isset($tmp[$el->name])) {
            $tmp = $tmp[$el->name];

            if ($tmp === Texy::ALL) {
                $el->setAttrs($this->attrs);

            } else {
                if (is_array($tmp) && count($tmp)) {
                    $tmp = array_flip($tmp);
                    foreach ($this->attrs as $key => $val)
                        if (isset($tmp[$key])) $el->attrs[$key] = $val;
                }
            }
        }

        $elAttrs = & $el->attrs;

        // title
        if ($this->title !== NULL)
            $elAttrs['title'] = $texy->typographyModule->postLine($this->title);

        // classes & ID
        if ($this->classes || $this->id !== NULL) {
            $tmp = $texy->_classes; // speed-up
            if ($tmp === Texy::ALL) {
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
            if ($tmp === Texy::ALL) {
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



    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }

} // TexyModifier
