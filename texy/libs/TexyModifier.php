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

    /** @var Texy */
    protected $texy;

    public $id;
    public $classes = array();
    public $styles = array();
    public $attrs = array();
    public $hAlign;
    public $vAlign;
    public $title;

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



    public function __construct($texy)
    {
        $this->texy =  $texy;
    }



    public function setProperties()
    {
        $ea = self::$elAttrs;

        foreach (func_get_args() as $arg)
        {
            if ($arg == NULL) continue;

            $argX = trim(substr($arg, 1, -1));
            switch ($arg{0}) {
            case '(':
                if (strpos($argX, '&') !== FALSE) // speed-up
                    $argX = html_entity_decode($argX);
                $this->title = $argX;
                break;

            case '{':
                foreach (explode(';', $argX) as $value) {
                    $pair = explode(':', $value, 2); $pair[] = '';
                    $prop = strtolower(trim($pair[0]));
                    $value = trim($pair[1]);
                    if ($prop === '') continue;

                    if (isset($ea[$prop])) // attribute
                        $this->attrs[$prop] = $value;
                    elseif ($value !== '')  // style
                        $this->styles[$prop] = $value;
                }
                break;

            case '[':
                $argX = str_replace('#', ' #', $argX);
                foreach (explode(' ', $argX) as $value) {
                    if ($value === '') continue;

                    if ($value{0} === '#')
                        $this->id = substr($value, 1);
                    else
                        $this->classes[] = $value;
                }
                break;

            case '^':  $this->vAlign = self::VALIGN_TOP; break;
            case '-':  $this->vAlign = self::VALIGN_MIDDLE; break;
            case '_':  $this->vAlign = self::VALIGN_BOTTOM; break;
            case '=':  $this->hAlign = self::HALIGN_JUSTIFY; break;
            case '>':  $this->hAlign = self::HALIGN_RIGHT; break;
            case '<':  $this->hAlign = $arg === '<>' ? self::HALIGN_CENTER : self::HALIGN_LEFT; break;
            }
        }
    }



    /**
     * Generates TexyHtml element
     * @param string
     * @return TexyHtml
     */
    public function generate($tag)
    {
        // tag & attibutes
        $tmp = $this->texy->allowedTags; // speed-up
        if ($tmp === Texy::ALL) {
            $el = TexyHtml::el($tag, $this->attrs);

        } elseif (is_array($tmp) && isset($tmp[$tag])) {
            $tmp = $tmp[$tag];

            if ($tmp === Texy::ALL) {
                $el = TexyHtml::el($tag, $this->attrs);

            } else {
                $el = TexyHtml::el($tag);

                if (is_array($tmp) && count($tmp)) {
                    $tmp = array_flip($tmp);
                    foreach ($this->attrs as $key => $val)
                        if (isset($tmp[$key])) $el->$key = $val;
                }
            }
        } else {
            $el = TexyHtml::el($tag);
        }

        // HACK (move to front)
        $el->href = $el->src = NULL;


        // title
        $el->title = $this->title;

        // classes & ID
        $tmp = $this->texy->_classes; // speed-up
        if ($tmp === Texy::ALL) {
            foreach ($this->classes as $val) $el->class[] = $val;
            $el->id = $this->id;
        } elseif (is_array($tmp)) {
            foreach ($this->classes as $val)
                if (isset($tmp[$val])) $el->class[] = $val;

            if (isset($tmp['#' . $this->id])) $el->id = $this->id;
        }

        // styles
        $tmp = $this->texy->_styles;  // speed-up
        if ($tmp === Texy::ALL) {
            foreach ($this->styles as $prop => $val) $el->style[$prop] = $val;
        } elseif (is_array($tmp)) {
            foreach ($this->styles as $prop => $val)
                if (isset($tmp[$prop])) $el->style[$prop] = $val;
        }

        // align
        if ($this->hAlign) $el->style['text-align'] = $this->hAlign;
        if ($this->vAlign) $el->style['vertical-align'] = $this->vAlign;

        return $el;
    }



    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }
    private function __unset($nm) { $this->__get($nm); }
    private function __isset($nm) { $this->__get($nm); }

} // TexyModifier
