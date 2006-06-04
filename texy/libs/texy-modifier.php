<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://www.texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 * @package    Texy
 * @category   Text
 * @version    1.0 for PHP4 & PHP5 (released 2006/04/18)
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
class TexyModifier {
    var $texy; // parent Texy! object
    var $id;
    var $classes = array();
    var $unfilteredClasses = array();
    var $styles = array();
    var $unfilteredStyles = array();
    var $unfilteredAttrs = array();
    var $hAlign;
    var $vAlign;
    var $title;



    function TexyModifier(& $texy)
    {
        $this->texy = & $texy;
    }




    function setProperties()
    {
        $classes = '';
        $styles  = '';

        foreach (func_get_args() as $arg) {
            if ($arg == '') continue;
            $argX = trim(substr($arg, 1, -1));
            switch ($arg{0}) {
                case '{' :  $styles .= $argX . ';';  break;
                case '(' :  $this->title = $argX; break;
                case '[' :  $classes .= ' '.$argX; break;
                case '^' :  $this->vAlign = TEXY_VALIGN_TOP; break;
                case '-' :  $this->vAlign = TEXY_VALIGN_MIDDLE; break;
                case '_' :  $this->vAlign = TEXY_VALIGN_BOTTOM; break;
                case '=' :  $this->hAlign = TEXY_HALIGN_JUSTIFY; break;
                case '>' :  $this->hAlign = TEXY_HALIGN_RIGHT; break;
                case '<' :  $this->hAlign = $arg == '<>' ? TEXY_HALIGN_CENTER : TEXY_HALIGN_LEFT; break;
            }
        }

        $this->parseStyles($styles);
        $this->parseClasses($classes);

        if (isset($this->classes['id'])) {
            $this->id = $this->classes['id'];
            unset($this->classes['id']);
        }
    }


    function getAttrs($tag)
    {
        if ($this->texy->allowedTags === TEXY_ALL)
            return $this->unfilteredAttrs;

        if (is_array($this->texy->allowedTags) && isset($this->texy->allowedTags[$tag])) {
            $allowedAttrs = $this->texy->allowedTags[$tag];

            if ($allowedAttrs === TEXY_ALL)
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


    function clear()
    {
        $this->id = null;
        $this->classes = array();
        $this->unfilteredClasses = array();
        $this->styles = array();
        $this->unfilteredStyles = array();
        $this->unfilteredAttrs = array();
        $this->hAlign = null;
        $this->vAlign = null;
        $this->title = null;
    }


    function copyFrom(&$modifier)
    {
        $this->classes = $modifier->classes;
        $this->unfilteredClasses = $modifier->unfilteredClasses;
        $this->styles = $modifier->styles;
        $this->unfilteredStyles = $modifier->unfilteredStyles;
        $this->unfilteredAttrs = $modifier->unfilteredAttrs;
        $this->id = $modifier->id;
        $this->hAlign = $modifier->hAlign;
        $this->vAlign = $modifier->vAlign;
        $this->title = $modifier->title;
    }





    function parseClasses($str)
    {
        if ($str == null) return;

        $tmp = is_array($this->texy->allowedClasses) ? array_flip($this->texy->allowedClasses) : array(); // little speed-up trick

        foreach (explode(' ', str_replace('#', ' #', $str)) as $value) {
            if ($value === '') continue;

            if ($value{0} == '#') {
                $this->unfilteredClasses['id'] = substr($value, 1);
                if ($this->texy->allowedClasses === TEXY_ALL || isset($tmp[$value]))
                    $this->classes['id'] = substr($value, 1);

            } else {
                $this->unfilteredClasses[] = $value;
                if ($this->texy->allowedClasses === TEXY_ALL || isset($tmp[$value]))
                    $this->classes[] = $value;
            }
        }
    }





    function parseStyles($str)
    {
        if ($str == null) return;

        $tmp = is_array($this->texy->allowedStyles) ? array_flip($this->texy->allowedStyles) : array(); // little speed-up trick
        static $TEXY_ACCEPTED_ATTRS;
        if (!$TEXY_ACCEPTED_ATTRS) $TEXY_ACCEPTED_ATTRS = unserialize(TEXY_ACCEPTED_ATTRS);

        foreach (explode(';', $str) as $value) {
            $pair = explode(':', $value.':');
            $property = strtolower(trim($pair[0]));
            $value = trim($pair[1]);
            if ($property == '') continue;

            if (isset($TEXY_ACCEPTED_ATTRS[$property])) { // attribute
                $this->unfilteredAttrs[$property] = $value;

            } else { // style
                $this->unfilteredStyles[$property] = $value;
                if ($this->texy->allowedStyles === TEXY_ALL || isset($tmp[$property]))
                    $this->styles[$property] = $value;
            }
        }
    }


} // TexyModifier






?>