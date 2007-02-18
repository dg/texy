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
 * HORIZONTAL LINE MODULE CLASS
 */
class TexyHorizLineModule extends TexyModule
{
    /** @var callback    Callback that will be called with newly created element */
    public $handler;

    /**
     * Module initialization.
     */
    public function init()
    {
        $this->texy->registerBlockPattern($this, 'processBlock', '#^(\- |\-|\* |\*){3,}\ *'.TEXY_MODIFIER_H.'?()$#mU');
    }



    /**
     * Callback function (for blocks)
     *
     *            ---------------------------
     *
     *            - - - - - - - - - - - - - -
     *
     *            ***************************
     *
     *            * * * * * * * * * * * * * *
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

        $el = new TexyBlockElement($this->texy);
        $el->tag = 'hr';
        $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

        if ($this->handler)
            if (call_user_func_array($this->handler, array($el)) === FALSE) return;

        $parser->element->appendChild($el);
    }




} // TexyHorizlineModule
