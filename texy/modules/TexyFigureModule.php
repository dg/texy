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
 * The captioned figures
 */
class TexyFigureModule extends TexyModule
{
    protected $default = array('figure' => TRUE);

    /** @var string  non-floated box CSS class */
    public $class = 'figure';

    /** @var string  left-floated box CSS class */
    public $leftClass = 'figure-left';

    /** @var string  right-floated box CSS class */
    public $rightClass = 'figure-right';

    /** @var int  how calculate div's width */
    public $widthDelta = 10;


    public function init()
    {
        $this->texy->registerBlockPattern(
            array($this, 'processBlock'),
            '#^'.TEXY_IMAGE.TEXY_LINK_N.'?? +\*\*\* +(.*)'.TEXY_MODIFIER_H.'?()$#mU',
            'figure'
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
        $user = $link = NULL;

        $image = $tx->imageModule->parse($mURLs, $mImgMod1, $mImgMod2, $mImgMod3, $mImgMod4);

        $mod = new TexyModifier;
        $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

        if ($mLink) {
            if ($mLink === ':') {
                $link = new TexyLink;
                $link->URL = $image->linkedURL === NULL ? $image->imageURL : $image->linkedURL;
                $link->type = TexyLink::AUTOIMAGE;
                $link->modifier = new TexyModifier;
            } else {
                $link = $tx->linkModule->parse($mLink, NULL, NULL, NULL, NULL);
            }
        }

        if (is_callable(array($tx->handler, 'figure'))) {
            $el = $tx->handler->figure($tx, $image, $link, $mContent, $mod, $user);
            if ($el) return $el;
        }

        $hAlign = $image->modifier->hAlign;
        $mod->hAlign = $image->modifier->hAlign = NULL;

        $elImg = $tx->imageModule->factory($image);
        $tx->summary['images'][] = $elImg->src;

        $el = TexyHtml::el('div');
        if (!empty($elImg->width)) $el->style['width'] = ($elImg->width + $this->widthDelta) . 'px';
        $mod->decorate($tx, $el);

        if ($link) {
            $elImg = $tx->linkModule->factory($link)->setContent($elImg);
            $tx->summary['links'][] = $elImg->href;
        }

        $el->childNodes['img'] = $elImg;
        $el->childNodes['caption'] = TexyHtml::el('');
        $el->childNodes['caption']->parseBlock($tx, ltrim($mContent));

        if ($hAlign === TexyModifier::HALIGN_LEFT) {
            $el->class[] = $this->leftClass;
        } elseif ($hAlign === TexyModifier::HALIGN_RIGHT)  {
            $el->class[] = $this->rightClass;
        } elseif ($this->class)
            $el->class[] = $this->class;

        if (is_callable(array($tx->handler, 'figure2')))
            $tx->handler->figure2($tx, $el, $user);

        $parser->children[] = $el;
    }

}
