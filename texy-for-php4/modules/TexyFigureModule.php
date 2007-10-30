<?php

/**
 * Texy! - web text markup-language (for PHP 4)
 * --------------------------------------------
 *
 * Copyright (c) 2004, 2007 David Grudl aka -dgx- (http://www.dgx.cz)
 *
 * This source file is subject to the GNU GPL license that is bundled
 * with this package in the file license.txt.
 *
 * For more information please see http://texy.info/
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004, 2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @category   Text
 * @package    Texy
 * @link       http://texy.info/
 */



/**
 * The captioned figures
 * @package Texy
 * @version $Revision$ $Date$
 */
class TexyFigureModule extends TexyModule
{
    /** @var string  non-floated box CSS class */
    var $class = 'figure';

    /** @var string  left-floated box CSS class */
    var $leftClass;

    /** @var string  right-floated box CSS class */
    var $rightClass;

    /** @var int  how calculate div's width */
    var $widthDelta = 10;


    function __construct($texy)
    {
        $this->texy = $texy;

        $texy->addHandler('figure', array($this, 'solve'));

        $texy->registerBlockPattern(
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
    function pattern($parser, $matches)
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
                $link->type = TEXY_LINK_IMAGE;
            } else {
                $link = $tx->linkModule->factoryLink($mLink, NULL, NULL);
            }
        } else $link = NULL;

        return $tx->invokeAroundHandlers('figure', $parser, array($image, $link, $mContent, $mod));
    }



    /**
     * Finish invocation
     *
     * @param TexyHandlerInvocation  handler invocation
     * @param TexyImage
     * @param TexyLink
     * @param string
     * @param TexyModifier
     * @return TexyHtml|FALSE
     */
    function solve($invocation, /*TexyImage*/ $image, $link, $content, $mod)
    {
        $tx = $this->texy;

        $hAlign = $image->modifier->hAlign;
        $image->modifier->hAlign = NULL;

        $elImg = $tx->imageModule->solve(NULL, $image, $link); // returns TexyHtml or false!
        if (!$elImg) return FALSE;

        $el = TexyHtml::el('div');
        if (!empty($image->width) && $this->widthDelta !== FALSE) {
            $el->attrs['style']['width'] = ($image->width + $this->widthDelta) . 'px';
        }
        $mod->decorate($tx, $el);

        $el->offsetSet(0, $elImg);
        $el->offsetSet(1, $elP = TexyHtml::el('p'));
        $elP->parseLine($tx, ltrim($content));

        $class = $this->class;
        if ($hAlign) {
            $var = $hAlign . 'Class'; // leftClass, rightClass
            if (!empty($this->$var)) {
                $class = $this->$var;

            } elseif (empty($tx->alignClasses[$hAlign])) {
                $el->attrs['style']['float'] = $hAlign;

            } else {
                $class .= '-' . $tx->alignClasses[$hAlign];
            }
        }
        $el->attrs['class'][] = $class;

        return $el;
    }

}
