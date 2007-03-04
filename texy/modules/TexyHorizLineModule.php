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
 * Horizontal line module
 */
class TexyHorizLineModule extends TexyModule
{
    protected $default = array('horizline' => TRUE);


    public function init()
    {
        $this->texy->registerBlockPattern(
            array($this, 'pattern'),
            '#^(\- |\-|\* |\*){3,}\ *'.TEXY_MODIFIER_H.'?()$#mU',
            'horizline'
        );
    }



    /**
     * Callback for: -------
     *
     * @param TexyBlockParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml  or FALSE when not accepted
     */
    public function pattern($parser, $matches)
    {
        list(, , $mMod) = $matches;
        //    [1] => ---
        //    [2] => .(title)[class]{style}<>

        $el = TexyHtml::el('hr');
        $mod = new TexyModifier($mMod);
        $mod->decorate($this->texy, $el);
        return $el;
    }

} // TexyHorizlineModule
