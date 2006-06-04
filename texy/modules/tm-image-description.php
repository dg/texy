<?php

/**
 * -------------------------------------------------
 *   IMAGE WITH DESCRIPTION - TEXY! DEFAULT MODULE
 * -------------------------------------------------
 *
 * Version 1 Release Candidate
 *
 * DEPENDENCES: tm_image.php
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
require_once('tm-image.php');




/**
 * IMAGE WITH DESCRIPTION MODULE CLASS
 */
class TexyImageDescModule extends TexyModule {
  var $boxClass   = 'image';        // non-floated box class
  var $leftClass  = 'image left';   // left-floated box class
  var $rightClass = 'image right';  // right-floated box class

  /***
   * Module initialization.
   */
  function init()
  {
    $this->registerBlockPattern('processBlock', '#^'.TEXY_PATTERN_IMAGE.TEXY_PATTERN_LINK_N.'? +\*\*\* +(.*)MODIFIER_H?()$#mU');
  }



  /***
   * Callback function (for blocks)
   *
   *            [*image*]:link *** .... .(title)[class]{style}>
   *
   */
  function processBlock(&$blockParser, &$matches)
  {
    list($match, $mURLs, $mImgMod1, $mImgMod2, $mImgMod3, $mImgMod4, $mLink, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
    //    [1] => URLs
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => >
    //    [6] => url | [ref] | [*image*]
    //    [7] => ...
    //    [8] => (title)
    //    [9] => [class]
    //    [10] => {style}
    //    [11] => >

    $el = &new TexyImageDescElement($this->texy);
    $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

    if ($this->texy->images->allowed) {
      $el->children['img']->setImagesRaw($mURLs);
      $el->children['img']->modifier->setProperties($mImgMod1, $mImgMod2, $mImgMod3, $mImgMod4);
      $el->modifier->hAlign = $el->children['img']->modifier->hAlign;
      $el->children['img']->modifier->hAlign = null;
    }

    $el->children['desc']->parse(ltrim($mContent));

    $blockParser->addChildren($el);
  }




} // TexyImageModule










/****************************************************************************
                               TEXY! DOM ELEMENTS                          */







/**
 * HTML ELEMENT IMAGE (WITH DESCRIPTION)
 */
class TexyImageDescElement extends TexyBlockElement {
  var $parentModule;


  // constructor
  function TexyImageDescElement(&$texy)
  {
    parent::TexyBlockElement($texy);
    $this->parentModule = & $texy->modules['TexyImageDescModule'];

    $this->children['img'] = &new TexyImageElement($texy);
    $this->children['desc'] = &new TexyGenericBlockElement($texy);
  }



  function generateTag(&$tag, &$attr)
  {
    $tag = 'div';

    $classes = $this->modifier->classes;
    $styles = $this->modifier->styles;

    if ($this->modifier->hAlign == TEXY_HALIGN_LEFT) {
      $classes[] = $this->parentModule->leftClass;

    } elseif ($this->modifier->hAlign == TEXY_HALIGN_RIGHT)  {
      $classes[] = $this->parentModule->rightClass;

    } elseif ($this->parentModule->boxClass)
      $classes[] = $this->parentModule->boxClass;

    $attr['class'] = TexyModifier::implodeClasses($classes);
    $attr['style'] = TexyModifier::implodeStyles($styles);
    $attr['id'] = $this->modifier->id;
  }



}  // TexyImageDescElement


?>