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
    public $unfilteredClasses = array();
    public $styles = array();
    public $unfilteredStyles = array();
    public $unfilteredAttrs = array();
    public $hAlign;
    public $vAlign;
    public $title;



    public function __construct($texy)
    {
        $this->texy =  $texy;
    }


    public function setProperties()
    {
        foreach (func_get_args() as $arg) {
            if ($arg == '') continue;
            $argX = trim(substr($arg, 1, -1));
            switch ($arg{0}) {
                case '{':  $this->parseStyles($argX); break;
                case '(':  $this->title = html_entity_decode($argX); break;
                case '[':  $this->parseClasses($argX); break;
                case '^':  $this->vAlign = self::VALIGN_TOP; break;
                case '-':  $this->vAlign = self::VALIGN_MIDDLE; break;
                case '_':  $this->vAlign = self::VALIGN_BOTTOM; break;
                case '=':  $this->hAlign = self::HALIGN_JUSTIFY; break;
                case '>':  $this->hAlign = self::HALIGN_RIGHT; break;
                case '<':  $this->hAlign = $arg === '<>' ? self::HALIGN_CENTER : self::HALIGN_LEFT; break;
            }
        }
    }


    public function getAttrs($tag)
    {
        if ($this->texy->allowedTags === Texy::ALL)
            return $this->unfilteredAttrs;

        if (is_array($this->texy->allowedTags) && isset($this->texy->allowedTags[$tag])) {
            $allowedAttrs = $this->texy->allowedTags[$tag];

            if ($allowedAttrs === Texy::ALL)
                return $this->unfilteredAttrs;

            if (is_array($allowedAttrs) && count($allowedAttrs)) {
                $attrs = $this->unfilteredAttrs;
                foreach ($attrs as $key => $foo)
                    if (!in_array($key, $allowedAttrs)) unset($attrs[$key]);

                return $attrs;
            }
        }

        return array();
    }




    public function parseClasses($str)
    {
        if ($str == NULL) return;

        $tmp = is_array($this->texy->allowedClasses) ? array_flip($this->texy->allowedClasses) : array(); // little speed-up trick

        $str = str_replace('#', ' #', $str);
        foreach (explode(' ', $str) as $value) {
            if ($value === '') continue;

            if ($value{0} == '#') {
                $this->unfilteredClasses['id'] = substr($value, 1);
                if ($this->texy->allowedClasses === Texy::ALL || isset($tmp[$value]))
                    $this->id = substr($value, 1);

            } else {
                $this->unfilteredClasses[] = $value;
                if ($this->texy->allowedClasses === Texy::ALL || isset($tmp[$value]))
                    $this->classes[] = $value;
            }
        }
    }





    public function parseStyles($str)
    {
        if ($str == NULL) return;

        $tmp = is_array($this->texy->allowedStyles) ? array_flip($this->texy->allowedStyles) : array(); // little speed-up trick

        foreach (explode(';', $str) as $value) {
            $pair = explode(':', $value.':');
            $property = strtolower(trim($pair[0]));
            $value = trim($pair[1]);
            if ($property == '') continue;

            if (isset(TexyHtml::$accepted_attrs[$property])) { // attribute
                $this->unfilteredAttrs[$property] = $value;

            } else { // style
                $this->unfilteredStyles[$property] = $value;
                if ($this->texy->allowedStyles === Texy::ALL || isset($tmp[$property]))
                    $this->styles[$property] = $value;
            }
        }
    }


    public function decorate(TexyHtml $el)
    {
        foreach ($this->getAttrs($el->element) as $attr => $val) $el->$attr = $val;

        $el->id = $this->id;
        $el->title = $this->title;
        $el->class = $this->classes;
        $el->style = $this->styles;
        if ($this->hAlign) $el->style['text-align'] = $this->hAlign;
        if ($this->vAlign) $el->style['vertical-align'] = $this->vAlign;

        if ($el->element === 'a') {
            // rel="nofollow"
            if (in_array('nofollow', $this->unfilteredClasses)) {
                $el->rel = 'nofollow'; // TODO: append, not replace
                if (($pos = array_search('nofollow', $el->class)) !== FALSE)
                     unset($el->class[$pos]);
            }

            // popup on click
            if (in_array('popup', $this->unfilteredClasses)) {
                $el->onclick = $this->texy->linkModule->popupOnClick;
                if (($pos = array_search('popup', $el->class)) !== FALSE)
                     unset($el->class[$pos]);
            }
        }

    }


    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }
    private function __unset($nm) { $this->__get($nm); }
    private function __isset($nm) { $this->__get($nm); }

} // TexyModifier
