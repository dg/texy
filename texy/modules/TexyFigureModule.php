<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * Copyright (c) 2004-2007 David Grudl aka -dgx- <dave@dgx.cz>
 *
 * @version  $Revision$ $Date$
 * @package  Texy
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
    public $leftClass;

    /** @var string  right-floated box CSS class */
    public $rightClass;

    /** @var int  how calculate div's width */
    public $widthDelta = 10;


    public function begin()
    {
        $this->texy->registerBlockPattern(
            array($this, 'pattern'),
            '#^'.TEXY_IMAGE.TEXY_LINK_N.'?? +\*\*\* +(.*)'.TEXY_MODIFIER_H.'?()$#mUu',
            'figure'
        );
    }



    /**
     * Callback for [*image*]:link *** .... .(title)[class]{style}>
     *
     * @param TexyBlockParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|string|FALSE
     */
    public function pattern($parser, $matches)
    {
        list(, $mURLs, $mImgMod, $mAlign, $mLink, $mContent, $mMod) = $matches;
        //    [1] => URLs
        //    [2] => .(title)[class]{style}<>
        //    [3] => * < >
        //    [4] => url | [ref] | [*image*]
        //    [5] => ...
        //    [6] => .(title)[class]{style}<>

        $tx = $this->texy;
        $image = $tx->imageModule->factoryImage($mURLs, $mImgMod.$mAlign);
        $mod = new TexyModifier($mMod);
        $mContent = ltrim($mContent);

        if ($mLink) {
            if ($mLink === ':') {
                $link = new TexyLink($image->linkedURL === NULL ? $image->URL : $image->linkedURL);
                $link->raw = ':';
                $link->type = TexyLink::IMAGE;
            } else {
                $link = $tx->linkModule->factoryLink($mLink, NULL, NULL);
            }
        } else $link = NULL;

        // event wrapper
        if (is_callable(array($tx->handler, 'figure'))) {
            $res = $tx->handler->figure($parser, $image, $link, $mContent, $mod);
            if ($res !== Texy::PROCEED) return $res;
        }

        return $this->solve($image, $link, $mContent, $mod);
    }



    /**
     * Finish invocation
     *
     * @param TexyImage
     * @param TexyLink
     * @param string
     * @param TexyModifier
     * @return TexyHtml
     */
    public function solve(TexyImage $image, $link, $content, $mod)
    {
        $tx = $this->texy;

        $hAlign = $image->modifier->hAlign;
        $mod->hAlign = $image->modifier->hAlign = NULL;

        $elImg = $tx->imageModule->solve($image, $link); // returns TexyHtml or false!

        $el = TexyHtml::el('div');
        if (!empty($image->width)) $el->style['width'] = ($image->width + $this->widthDelta) . 'px';
        $mod->decorate($tx, $el);

        $el->childNodes['img'] = $elImg;
        $el->childNodes['caption'] = TexyHtml::el('p');
        $el->childNodes['caption']->parseLine($tx, ltrim($content));

        if ($hAlign === TexyModifier::HALIGN_LEFT) {
            if ($this->leftClass != '')
                $el->class[] = $this->leftClass;
            else
                $el->style['float'] = 'left';

        } elseif ($hAlign === TexyModifier::HALIGN_RIGHT)  {

            if ($this->rightClass != '')
                $el->class[] = $this->rightClass;
            else
                $el->style['float'] = 'right';
        } elseif ($this->class)
            $el->class[] = $this->class;

        return $el;
    }

}
