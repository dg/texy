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
 * QUOTE & BLOCKQUOTE MODULE CLASS
 */
class TexyQuoteModule extends TexyModule
{
    protected $allow = array('Blockquote');


    /**
     * Module initialization.
     */
    public function init()
    {
        $this->texy->registerBlockPattern(
            $this,
            'processBlock',
            '#^(?:'.TEXY_MODIFIER_H.'\n)?\>(\ +|:)(\S.*)$#mU',
            'Blockquote'
        );
    }




    /**
     * Callback function (for blocks)
     *
     *            > They went in single file, running like hounds on a strong scent,
     *            and an eager light was in their eyes. Nearly due west the broad
     *            swath of the marching Orcs tramped its ugly slot; the sweet grass
     *            of Rohan had been bruised and blackened as they passed.
     *            >:http://www.mycom.com/tolkien/twotowers.html
     *
     */
    public function processBlock($parser, $matches)
    {
        list(, $mMod1, $mMod2, $mMod3, $mMod4, $mSpaces, $mContent) = $matches;
        //    [1] => (title)
        //    [2] => [class]
        //    [3] => {style}
        //    [4] => <>
        //    [5] => spaces |
        //    [6] => ... / LINK

        $el = new TexyBlockElement($this->texy);

        if ($mMod1 || $mMod2 || $mMod3 || $mMod4) {
            $mod = new TexyModifier($this->texy);
            $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
            $el->tags[0] = $mod->generate('blockquote');
        } else {
            $el->tags[0] = TexyHtml::el('blockquote');
        }

        $content = '';
        $linkTarget = '';
        $spaces = '';
        do {
            if ($mSpaces === ':') $linkTarget = trim($mContent);
            else {
                if ($spaces === '') $spaces = max(1, strlen($mSpaces));
                $content .= $mContent . "\n";
            }

            if (!$parser->receiveNext("#^>(?:|(\\ {1,$spaces}|:)(.*))()$#mA", $matches)) break;
            list(, $mSpaces, $mContent) = $matches;
        } while (TRUE);

        if ($linkTarget) {
            $el->tags[0]->cite = $linkTarget;
            // TODO
            /*
            $elx = new TexyLinkElement($this->texy);
            $elx->setLinkRaw($linkTarget);
            $el->cite->set($elx->link->asURL());
            */
        }

        $el->parse($content);

        $parser->element->children[] = $el;
    }



} // TexyQuoteModule



