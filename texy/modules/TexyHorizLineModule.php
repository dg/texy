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
    protected $default = array('horizLine' => TRUE);


    public function init()
    {
        $this->texy->registerBlockPattern(
            array($this, 'processBlock'),
            '#^(\- |\-|\* |\*){3,}\ *'.TEXY_MODIFIER_H.'?()$#mU',
            'horizLine'
        );
    }



    /**
     * Callback function
     *
     *   ---------------------------
     *
     *   - - - - - - - - - - - - - -
     *
     *   ***************************
     *
     *   * * * * * * * * * * * * * *
     *
     */
    public function processBlock($parser, $matches)
    {
        list(, , $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
        //    [1] => ---
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => >

        $el = TexyHtml::el('hr');
        $mod = new TexyModifier;
        $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $mod->decorate($this->texy, $el);

        $parser->children[] = $el;
    }

} // TexyHorizlineModule
