<?php

/**
 * Texy! - web text markup-language
 * --------------------------------
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
 * Horizontal line module
 * @package Texy
 * @version $Revision$ $Date$
 */
final class TexyHorizLineModule extends TexyModule
{

    public function __construct($texy)
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
    public function pattern($parser, $matches)
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
    public function solve($invocation, $type, $modifier)
    {
        $el = TexyHtml::el('hr');
        $modifier->decorate($invocation->getTexy(), $el);
        return $el;
    }

}
