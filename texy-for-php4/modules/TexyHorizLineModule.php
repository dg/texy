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
if (!class_exists('Texy')) die();



/**
 * Horizontal line module
 */
class TexyHorizLineModule extends TexyModule
{
    var $syntax = array('horizline' => TRUE); /* protected */


    function begin()
    {
        $this->texy->registerBlockPattern(
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

        // event listener
        if (is_callable(array($tx->handler, 'afterHorizline')))
            $tx->handler->afterHorizline($parser, $el, $mod);

        return $el;
    }

} // TexyHorizlineModule
