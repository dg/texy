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
 */
class TexyHorizLineModule extends TexyModule
{

    function __construct($texy)
    {
        $this->texy = $texy;

        $texy->registerBlockPattern(
            array($this, 'pattern'),
            '#^(?:\*{3,}|-{3,})\ *'.TEXY_MODIFIER.'?()$#mU',
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
        list(, $mMod) = $matches;
        //    [1] => ---
        //    [2] => .(title)[class]{style}<>

        $tx = $this->texy;
        $el = TexyHtml::el('hr');
        $mod = new TexyModifier($mMod);
        $mod->decorate($tx, $el);

        $tx->invokeHandlers('afterHorizline', array($parser, $el, $mod));

        return $el;
    }

}
