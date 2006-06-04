<?php

/**
 * -------------------------------
 *   SMILEYS - TEXY! USER MODULE
 * -------------------------------
 *
 * Version 0.9 beta
 *
 * Copyright (c) 2004-2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
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
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * AUTOMATIC REPLACEMENTS MODULE CLASS
 */
class TexySmileysModule extends TexyModule {
  var $icons = array (
        ':-)'     => 'smile.gif',
        ':-('     => 'sad.gif',
        ';-)'     => 'wink.gif',
        ':oops:'  => 'redface.gif',
        ':-D'     => 'biggrin.gif',
        '8-O'     => 'eek.gif',
        '8-)'     => 'cool.gif',
        ':-?'     => 'confused.gif',
        ':-x'     => 'mad.gif',
        ':-P'     => 'razz.gif',
        ':-|'     => 'neutral.gif',
        );
  var $iconsRoot = 'images/smileys/';
  var $class = '';



  /***
   * Module initialization.
   */
  function init() {
    $re = array();
    foreach ($this->icons as $key => $value) $re[] = preg_quote($key);
    $crazyRE = '#(?<=^|[\\x00-\\x20])(' . implode('|', $re) . ')#';

    $this->registerLinePattern('processLine', $crazyRE);
  }



  /***
   * Callback function: :-)
   * @return string
   */
  function processLine(&$lineParser, &$matches) {
    $match = &$matches[0];
    //    [1] => **
    //    [2] => ...
    //    [3] => (title)
    //    [4] => [class]
    //    [5] => {style}
    //    [6] => LINK

    $texy = & $this->texy;
    $el = &new TexyInlineElement($texy);
    $el->tag = 'img';
    $el->modifier->extra['alt'] = $match;
    $el->modifier->extra['src'] = $this->iconsRoot . $this->icons[$match];
    $el->modifier->classes[] = $this->class;

    return $el->hash($lineParser->element);
  }



} // TexySmileysModule






?>