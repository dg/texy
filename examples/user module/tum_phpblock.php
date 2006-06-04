<?php

/**
 * ---------------------------------
 *   PHP BLOCK - TEXY! USER MODULE
 * ---------------------------------
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
 * CODE MODULE CLASS
 */
class TexyPHPCodeUserModule extends TexyModule {
  var $geshiPath;


  // constructor
  function TexyPHPCodeUserModule(&$texy)
  {
    parent::TexyModule($texy);
  }


  /***
   * Module initialization.
   */
  function init()
  {
    $this->registerBlockPattern('processBlock',   '#^<\?php\n(.*)\n\?>$#mUs');
  }



  /***
   * Callback function (for blocks)
   * @return object
   *
   *            <?php
   *              ....
   *              ....
   *            ?>
   *
   */
  function processBlock(&$blockParser, &$matches)
  {
    list($match, $mContent) = $matches;
    //    [1] => .... content


    // outdent
    $mContent = trim($mContent, "\n");
    $spaces = 0; while ($mContent{$spaces} == ' ') $spaces++;
    if ($spaces) $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

    $el = &new TexyTextualElement($this->texy);
    $el->tag = 'pre';
    $el->modifier->classes[] = 'php';
    $blockParser->addChildren($el);

    do {
      if (!class_exists('GeSHi')) break;

      $geshi = new GeSHi($mContent, 'php', $this->geshiPath.'geshi/');

      if ($geshi->error) break;

      // do syntax-highlighting
      $geshi->set_encoding(TEXY_UTF8 ? 'UTF-8' : 'ISO-8859-1');
      $geshi->set_header_type(GESHI_HEADER_PRE);
      $geshi->enable_classes();
      $geshi->set_overall_style('color: #000066; border: 1px solid #d0d0d0; background-color: #f0f0f0;', true);
      $geshi->set_line_style('font: normal normal 95% \'Courier New\', Courier, monospace; color: #003030;', 'font-weight: bold; color: #006060;', true);
      $geshi->set_code_style('color: #000020;', 'color: #000020;');
      $geshi->set_link_styles(GESHI_LINK, 'color: #000060;');
      $geshi->set_link_styles(GESHI_HOVER, 'background-color: #f0f000;');

      $out = $geshi->parse_code();
      if (TEXY_UTF8)  // double-check buggy GESHI, it sometimes produce not UTF-8 valid code :-((
        if ($out !== utf8_encode(utf8_decode($out))) break;

      // save generated stylesheet
      $this->texy->styleSheet .= $geshi->get_stylesheet();

      $el->setContent($out, true);
      return;

    } while (false);

    $mContent = '<code>' . nl2br( htmlSpecialChars($mContent) )  . '</code>';
    $el->setContent($mContent, true);
  }





} // TexyPHPCodeUserModule





?>