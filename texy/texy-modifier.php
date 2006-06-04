<?php

/**
 * ----------------------------
 *   TEXY! MODIFIER PROCESSOR
 * ----------------------------
 *
 * Version 0.9 beta
 *
 * Copyright (c) 2004-2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * See documentation on website http://www.texy.info/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
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
  var $hAlign;
  var $vAlign;
  var $title;
  var $styles = array();
  var $extra = array(); // some additional attributes


  function TexyModifier(& $texy) {
    $this->texy = & $texy;
  }



  function setProperties() {
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

    if ($classes && $this->texy->allowClasses) {
      foreach (explode(' ', trim($classes)) as $value) {
        if (is_array($this->texy->allowClasses) &&
            !in_array($value, $this->texy->allowClasses)) continue;

        if (substr($value, 0, 1) == '#') $this->id = substr($value, 1);
        else $this->classes[] = $value;
      }
    }


    if ($styles && $this->texy->allowStyles) {
      foreach (explode(';', $styles) as $value) {
        $pair = explode(':', $value.':');
        $property = strtolower(trim($pair[0]));
        $value = trim($pair[1]);
        if (!$property || !$value) continue;

        if (is_array($this->texy->allowStyles) &&
            !in_array($property, $this->texy->allowStyles)) continue;

        $this->styles[$property] = $value;
      }
    }

    switch ($this->hAlign) {
      case TEXY_HALIGN_LEFT:    $this->styles['text-align'] = 'left'; break;
      case TEXY_HALIGN_RIGHT:   $this->styles['text-align'] = 'right'; break;
      case TEXY_HALIGN_CENTER:  $this->styles['text-align'] = 'center'; break;
      case TEXY_HALIGN_JUSTIFY: $this->styles['text-align'] = 'justify'; break;
    }

    switch ($this->vAlign) {
      case TEXY_VALIGN_TOP:     $this->styles['vertical-align'] = 'top'; break;
      case TEXY_VALIGN_MIDDLE:  $this->styles['vertical-align'] = 'middle'; break;
      case TEXY_VALIGN_BOTTOM:  $this->styles['vertical-align'] = 'bottom'; break;
    }
  }


  function clear() {
    $this->id = null;
    $this->classes = array();
    $this->hAlign = null;
    $this->vAlign = null;
    $this->title = null;
    $this->styles = array();
    $this->extra = array();
  }


  function copyFrom(&$modifier) {
    $this->id = $modifier->id;
    $this->classes = $modifier->classes;
    $this->hAlign = $modifier->hAlign;
    $this->vAlign = $modifier->vAlign;
    $this->title = $modifier->title;
    $this->styles = $modifier->styles;
  }


  // generate elements attributes
  function toAttributes() {
    $classes = $this->classes;
    if (isset($this->extra['class']))
      $classes = array_merge($classes, explode(' ', $this->extra['class']));
    $classes = implode(' ', array_unique($classes) );

    $style = '';
    foreach ($this->styles as $key => $value)
      $style .= $key . ':' . $value . ';';
    if (isset($this->extra['style']))
      $style .= $this->extra['style'];

    return array_merge(
             array('id'    => $this->id,      // lowest priority
                   'title' => $this->title,
             ),

             $this->extra,                    // high priority

             array('class' => $classes,       // highest priority
                   'style' => $style
             )
           );
  }


} // TexyModifier






?>