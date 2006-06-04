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
 * Copyright (c) 2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * For the full copyright and license information, please view the COPYRIGHT
 * file that was distributed with this source code. If the COPYRIGHT file is
 * missing, please visit the Texy! homepage: http://www.texy.info
 *
 * @package Texy
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();
require_once dirname(__FILE__).'/tm-image.php';




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
        $this->registerBlockPattern('processBlock', '#^'.TEXY_PATTERN_IMAGE.TEXY_PATTERN_LINK_N.'?? +\*\*\* +(.*)MODIFIER_H?()$#mU');
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
        $blockParser->addChildren($el);

        if ($this->texy->imageModule->allowed) {
            $el->children['img']->setImagesRaw($mURLs);
            $el->children['img']->modifier->setProperties($mImgMod1, $mImgMod2, $mImgMod3, $mImgMod4);
            $el->modifier->hAlign = $el->children['img']->modifier->hAlign;
            $el->children['img']->modifier->hAlign = null;
        }

        $el->children['desc']->parse(ltrim($mContent));
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
        $this->parentModule = & $texy->imageDescModule;

        $this->children['img'] = &new TexyImageElement($texy);
        $this->children['desc'] = &new TexyGenericBlockElement($texy);
    }



    function generateTags(&$tags)
    {
        $attr['class'] = $this->modifier->classes;
        $attr['style'] = $this->modifier->styles;
        $attr['id'] = $this->modifier->id;

        if ($this->modifier->hAlign == TEXY_HALIGN_LEFT) {
            $attr['class'][] = $this->parentModule->leftClass;

        } elseif ($this->modifier->hAlign == TEXY_HALIGN_RIGHT)  {
            $attr['class'][] = $this->parentModule->rightClass;

        } elseif ($this->parentModule->boxClass)
            $attr['class'][] = $this->parentModule->boxClass;

        $tags['div'] = $attr;
    }



}  // TexyImageDescElement


?>