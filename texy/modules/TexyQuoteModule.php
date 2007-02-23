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
 * Blockquote module
 */
class TexyQuoteModule extends TexyModule
{
    protected $allow = array('BlockQuote');


    public function init()
    {
        $this->texy->registerBlockPattern(
            $this,
            'processBlock',
            '#^(?:'.TEXY_MODIFIER_H.'\n)?\>(\ +|:)(\S.*)$#mU',
            'BlockQuote'
        );
    }



    /**
     * Callback function (for blocks)
     *
     *   > They went in single file, running like hounds on a strong scent,
     *   and an eager light was in their eyes. Nearly due west the broad
     *   swath of the marching Orcs tramped its ugly slot; the sweet grass
     *   of Rohan had been bruised and blackened as they passed.
     *   >:http://www.mycom.com/tolkien/twotowers.html
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

        $tx = $this->texy;
        $el = new TexyBlockElement($tx);

        if ($mMod1 || $mMod2 || $mMod3 || $mMod4) {
            $mod = new TexyModifier($tx);
            $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
            $el->tags[0] = $mod->generate('blockquote');
        } else {
            $el->tags[0] = TexyHtmlEl::el('blockquote');
        }

        $content = '';
        $linkTarget = '';
        $spaces = '';
        do {
            if ($mSpaces === ':') {
                $el->tags[0]->cite = $tx->quoteModule->citeLink($mContent)->asURL();
                $content .= "\n";
            } else {
                if ($spaces === '') $spaces = max(1, strlen($mSpaces));
                $content .= $mContent . "\n";
            }

            if (!$parser->receiveNext("#^>(?:|(\\ {1,$spaces}|:)(.*))()$#mA", $matches)) break;
            list(, $mSpaces, $mContent) = $matches;
        } while (TRUE);

        $el->parse($content);

        $parser->element->children[] = $el;
    }



    /**
     * Converts cite destination to TexyLink
     * @param string
     * @return TexyLink
     */
    public function citeLink($dest)
    {
        $tx = $this->texy;
        // [ref]
        if ($dest{0} === '[') {
            $dest = substr($dest, 1, -1);
            $ref = $this->getReference($dest);
            if ($ref)
                $link = new TexyLink($ref['URL'], $tx->linkModule->root, TexyLink::DIRECT);
            else
                $link = new TexyLink($dest, $tx->linkModule->root, TexyLink::REFERENCE);

        } else { // direct URL
            $link = new TexyLink($dest, $tx->linkModule->root, TexyLink::DIRECT);
        }

        // handler
        if (is_callable(array($tx->handler, 'Cite'))) $tx->handler->Cite($link);

        return $link;
    }


} // TexyQuoteModule
