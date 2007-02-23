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
    protected $allow = array('imageDesc');

    /** @var string  non-floated box CSS class */
    public $boxClass = 'image';

    /** @var string  left-floated box CSS class */
    public $leftClass = 'image left';

    /** @var string  right-floated box CSS class */
    public $rightClass = 'image right';



    public function init()
    {
        $this->texy->registerBlockPattern(
            $this,
            'processBlock',
            '#^'.TEXY_IMAGE.TEXY_LINK_N.'?? +\*\*\* +(.*)'.TEXY_MODIFIER_H.'?()$#mU',
            'imageDesc'
        );
    }



    /**
     * Callback function (for blocks)
     *
     *   [*image*]:link *** .... .(title)[class]{style}>
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

        $tx = $this->texy;
        $el = new TexyBlockElement($tx);

        list($URL, $overURL, $width, $height, $imgMod) = $tx->imageModule->factory1($mURLs, $mImgMod1, $mImgMod2, $mImgMod3, $mImgMod4);

        $mod = new TexyModifier($tx);
        $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $hAlign = $imgMod->hAlign;
        $mod->hAlign = $imgMod->hAlign = NULL;

        $elImage = $tx->imageModule->factoryEl($URL, $overURL, $width, $height, $imgMod, $mLink);

        $el->tags[0] = $mod->generate('div');

        if ($hAlign === TexyModifier::HALIGN_LEFT) {
            $el->tags[0]->class[] = $this->leftClass;

        } elseif ($hAlign === TexyModifier::HALIGN_RIGHT)  {
            $el->tags[0]->class[] = $this->rightClass;

        } elseif ($tx->imageDescModule->boxClass)
            $el->tags[0]->class[] = $this->boxClass;


        $elImg = new TexyTextualElement($tx);
        $el->children[] = $elImg;

        $elDesc = new TexyBlockElement($tx);
        $elDesc->parse(ltrim($mContent));
        $el->children[] = $elDesc;

        if ($mLink) {
            if ($mLink === ':') {
                $elLink = $tx->linkModule->factoryEl(
                    new TexyUrl($URL, $tx->imageModule->linkedRoot, TexyUrl::IMAGE),
                    new TexyModifier($tx)
                );
            } else {
                $elLink = $tx->linkModule->factory($mLink, NULL, NULL, NULL, NULL);
            }
            $elLink->addChild($elImage);
            $elImg->content = $elLink->toTexy($tx);
        } else {
            $elImg->content = $elImage->toTexy($tx);
        }

        $parser->element->children[] = $el;
    }

} // TexyImageModule
