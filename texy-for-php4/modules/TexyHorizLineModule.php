<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004-2007 David Grudl aka -dgx- (http://www.dgx.cz)
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @version    $Revision$ $Date$
 * @category   Text
 * @package    Texy
 */



/**
 * Horizontal line module
 * @package Texy
 */
class TexyHorizLineModule extends TexyModule
{

    function __construct($texy)
    {
        $this->texy = $texy;

        $texy->addHandler('horizline', array($this, 'solve'));

        $texy->registerBlockPattern(
            array($this, 'pattern'),
            '#^(\*{3,}|-{3,})\ *'.TEXY_MODIFIER.'?()$#mU',
            'horizline'
        );
    }



    /**
     * Callback for: -------
     *
     * @param TexyBlockParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml
     */
    function pattern($parser, $matches)
    {
        list(, $mType, $mMod) = $matches;
        //    [1] => ---
        //    [2] => .(title)[class]{style}<>

        $mod = new TexyModifier($mMod);
        return $this->texy->invokeAroundHandlers('horizline', $parser, array($mType, $mod));
    }



    /**
     * Finish invocation
     *
     * @param TexyHandlerInvocation  handler invocation
     * @param string
     * @param TexyModifier
     * @return TexyHtml
     */
    function solve($invocation, $type, $modifier)
    {
        $el = TexyHtml::el('hr');
        $modifier->decorate($invocation->getTexy(), $el);
        return $el;
    }

}
