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
 * Image with description module
 */
class TexyImageDescModule extends TexyModule
{
    protected $allow = array('Image.desc');

    public $boxClass   = 'image';        // non-floated box class
    public $leftClass  = 'image left';   // left-floated box class
    public $rightClass = 'image right';  // right-floated box class



    public function init()
    {
        $this->texy->registerBlockPattern(
            $this,
            'processBlock',
            '#^'.TEXY_IMAGE.TEXY_LINK_N.'?? +\*\*\* +(.*)'.TEXY_MODIFIER_H.'?()$#mU',
            'Image.desc'
        );
    }



    /**
     * Callback function (for blocks)
     *
     *            [*image*]:link *** .... .(title)[class]{style}>
     *
     */
    public function processBlock($parser, $matches)
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

        $el = new TexyBlockElement($this->texy);

        $elImage = new TexyImageElement($this->texy);
        $elImage->setImagesRaw($mURLs);
        $elImage->modifier->setProperties($mImgMod1, $mImgMod2, $mImgMod3, $mImgMod4);
        //$elImage->setImagesRaw($mURLs);


        $mod = new TexyModifier($this->texy);
        $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $hAlign = $elImage->modifier->hAlign;
        $mod->hAlign = $elImage->modifier->hAlign = NULL;

        $el->tags[0] = $mod->generate('div');

        if ($hAlign === TexyModifier::HALIGN_LEFT) {
            $el->tags[0]->class[] = $this->leftClass;

        } elseif ($hAlign === TexyModifier::HALIGN_RIGHT)  {
            $el->tags[0]->class[] = $this->rightClass;

        } elseif ($this->texy->imageDescModule->boxClass)
            $el->tags[0]->class[] = $this->boxClass;


        $content = $this->texy->mark($elImage->__toString(), Texy::CONTENT_NONE); // !!!

        if ($mLink) {
/*
            $elLink = new TexyLinkElement($this->texy);
            if ($mLink === ':') {
                $elImage->requireLinkImage();
                if ($elImage->linkImage) $elLink->link->copyFrom($elImage->linkImage);
            } else {
                $elLink->setLinkRaw($mLink);
            }

            $keyOpen  = $this->texy->mark($elLink->opening(), Texy::CONTENT_NONE);
            $keyClose = $this->texy->mark($elLink->closing(), Texy::CONTENT_NONE);
            $content = $keyOpen . $content . $keyClose;
*/
        }
        $elImg = new TexyTextualElement($this->texy);
        $elImg->content = $content;
        $el->children[] = $elImg;

        $elDesc = new TexyBlockElement($this->texy);
        $elDesc->parse(ltrim($mContent));
        $el->children[] = $elDesc;

        $parser->element->children[] = $el;
    }




} // TexyImageModule



