<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://www.texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 * @package    Texy
 * @category   Text
 * @version    1.5 for PHP4 & PHP5 $Date$ $Revision$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();




/**
 * IMAGE WITH DESCRIPTION MODULE CLASS
 */
class TexyImageDescModule extends TexyModule
{
    /** @var callback    Callback that will be called with newly created element */
    var $handler;

    var $boxClass   = 'image';        // non-floated box class
    var $leftClass  = 'image left';   // left-floated box class
    var $rightClass = 'image right';  // right-floated box class

    /**
     * Module initialization.
     */
    function init()
    {
        if ($this->texy->imageModule->allowed)
            $this->texy->registerBlockPattern(
                $this,
                'processBlock',
                '#^'.TEXY_PATTERN_IMAGE.TEXY_PATTERN_LINK_N.'?? +\*\*\* +(.*)<MODIFIER_H>?()$#mU'
            );
    }



    /**
     * Callback function (for blocks)
     *
     *            [*image*]:link *** .... .(title)[class]{style}>
     *
     */
    function processBlock(&$parser, $matches)
    {
        list(, $mURLs, $mImgMod1, $mImgMod2, $mImgMod3, $mImgMod4, $mLink, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
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

        $elImage = &new TexyImageElement($this->texy);
        $elImage->setImagesRaw($mURLs);
        $elImage->modifier->setProperties($mImgMod1, $mImgMod2, $mImgMod3, $mImgMod4);

        $el->modifier->hAlign = $elImage->modifier->hAlign;
        $elImage->modifier->hAlign = NULL;

        $content = $el->appendChild($elImage);

        if ($mLink) {
            $elLink = &new TexyLinkElement($this->texy);
            if ($mLink == ':') {
                $elImage->requireLinkImage();
                $elLink->link->copyFrom($elImage->linkImage);
            } else {
                $elLink->setLinkRaw($mLink);
            }

            $content = $el->appendChild($elLink, $content);
        }

        $elDesc = &new TexyGenericBlockElement($this->texy);
        $elDesc->parse(ltrim($mContent));
        $content .= $el->appendChild($elDesc);
        $el->setContent($content, TRUE);

        if ($this->handler)
            if (call_user_func_array($this->handler, array(&$el)) === FALSE) return;

        $parser->element->appendChild($el);
    }




} // TexyImageModule














/**
 * HTML ELEMENT IMAGE (WITH DESCRIPTION)
 */
class TexyImageDescElement extends TexyTextualElement
{



    function generateTags(&$tags)
    {
        $attrs = $this->modifier->getAttrs('div');
        $attrs['class'] = $this->modifier->classes;
        $attrs['style'] = $this->modifier->styles;
        $attrs['id'] = $this->modifier->id;

        if ($this->modifier->hAlign == TEXY_HALIGN_LEFT) {
            $attrs['class'][] = $this->texy->imageDescModule->leftClass;

        } elseif ($this->modifier->hAlign == TEXY_HALIGN_RIGHT)  {
            $attrs['class'][] = $this->texy->imageDescModule->rightClass;

        } elseif ($this->texy->imageDescModule->boxClass)
            $attrs['class'][] = $this->texy->imageDescModule->boxClass;

        $tags['div'] = $attrs;
    }



}  // TexyImageDescElement


?>