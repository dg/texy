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
 * MODIFIER PROCESSOR
 * ------------------
 *
 * Modifier is text like .(title)[class1 class2 #id]{color: red}>^
 *   .         starts with dot
 *   (...)     title or alt modifier
 *   [...]     classes or ID modifier
 *   {...}     inner style modifier
 *   < > <> =  horizontal align modifier
 *   ^ - _     vertical align modifier
 *
 */
class TexyModifier
{
    const HALIGN_LEFT =      'left';
    const HALIGN_RIGHT =     'right';
    const HALIGN_CENTER =    'center';
    const HALIGN_JUSTIFY =   'justify';
    const VALIGN_TOP =       'top';
    const VALIGN_MIDDLE =    'middle';
    const VALIGN_BOTTOM =    'bottom';

    protected $texy; // parent Texy! object
    public $id;
    public $classes = array();
    public $styles = array();
    public $attrs = array();
    public $hAlign;
    public $vAlign;
    public $title;


    public function __construct($texy)
    {
        $this->texy =  $texy;
    }


    public function setProperties()
    {
        $acc = TexyHtml::$accepted_attrs;

        foreach (func_get_args() as $arg)
        {
            if ($arg == '') continue;

            $argX = trim(substr($arg, 1, -1));
            switch ($arg{0}) {
            case '(':
                $this->title = html_entity_decode($argX);
                break;

            case '{':
                foreach (explode(';', $argX) as $value) {
                    $pair = explode(':', $value.':');
                    $prop = strtolower(trim($pair[0]));
                    $value = trim($pair[1]);
                    if ($prop === '') continue;

                    if (isset($acc[$prop])) // attribute
                        $this->attrs[$prop] = $value;
                    else  // style
                        $this->styles[$prop] = $value;
                }
                break;

            case '[':
                $argX = str_replace('#', ' #', $argX);
                foreach (explode(' ', $argX) as $value) {
                    if ($value === '') continue;

                    if ($value{0} == '#')
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
        if ($this->texy->allowedTags === Texy::ALL) {
            $el = TexyHtml::el($tag, $this->attrs);

        } elseif (is_array($this->texy->allowedTags) && isset($this->texy->allowedTags[$tag])) {
            $allowed = $this->texy->allowedTags[$tag];

            if ($allowed === Texy::ALL) {
                $el = TexyHtml::el($tag, $this->attrs);

            } else {
                $el = TexyHtml::el($tag);
                unset($allowed['_name'], $allowed['_empty']);

                if (is_array($allowed) && count($allowed))
                    foreach ($this->attrs as $key => $val)
                        if (in_array($key, $allowed)) $el->$key = $val;
            }
        } else {
            $el = TexyHtml::el($tag);
        }


        // title
        $el->title = $this->title;

        // classes
        $tmp = is_array($this->texy->allowedClasses) ? array_flip($this->texy->allowedClasses) : array();
        foreach ($this->classes as $val) {
            if ($this->texy->allowedClasses === Texy::ALL || isset($tmp[$val]))
                $el->class[] = $val;
        }

        // id
        if ($this->texy->allowedClasses === Texy::ALL || isset($tmp['#' . $this->id]))
            $el->id = $this->id;

        // styles
        $tmp = is_array($this->texy->allowedStyles) ? array_flip($this->texy->allowedStyles) : array();
        foreach ($this->styles as $prop => $val) {
            if ($this->texy->allowedStyles === Texy::ALL || isset($tmp[$prop]))
                $el->style[$prop] = $val;
        }

        // align
        if ($this->hAlign) $el->style['text-align'] = $this->hAlign;
        if ($this->vAlign) $el->style['vertical-align'] = $this->vAlign;

        // special cases
        if ($tag === 'a') {
            // rel="nofollow"
            if (in_array('nofollow', $this->classes)) {
                $el->rel = 'nofollow'; // TODO: append, not replace
                if (($pos = array_search('nofollow', $el->class)) !== FALSE)
                     unset($el->class[$pos]);
            }

            // popup on click
            if (in_array('popup', $this->classes)) {
                $el->onclick = $this->texy->linkModule->popupOnClick;
                if (($pos = array_search('popup', $el->class)) !== FALSE)
                     unset($el->class[$pos]);
            }
        }

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
