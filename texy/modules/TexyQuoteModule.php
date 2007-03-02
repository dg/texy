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
    protected $default = array('blockQuote' => TRUE);


    public function init()
    {
        $this->texy->registerBlockPattern(
            array($this, 'processBlock'),
            '#^(?:'.TEXY_MODIFIER_H.'\n)?\>(\ +|:)(\S.*)$#mU',
            'blockQuote'
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

        $el = TexyHtml::el('blockquote');
        $mod = new TexyModifier;
        $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $mod->decorate($tx, $el);

        $content = '';
        $spaces = '';
        do {
            if ($mSpaces === ':') {
                $mod->cite = $tx->quoteModule->citeLink($mContent);
                $content .= "\n";
            } else {
                if ($spaces === '') $spaces = max(1, strlen($mSpaces));
                $content .= $mContent . "\n";
            }

            if (!$parser->receiveNext("#^>(?:|(\\ {1,$spaces}|:)(.*))()$#mA", $matches)) break;
            list(, $mSpaces, $mContent) = $matches;
        } while (TRUE);

        $el->cite = $mod->cite;
        $el->parseBlock($tx, $content);

        $parser->children[] = $el;
    }



    /**
     * Converts cite source to URL
     * @param string
     * @return string
     */
    public function citeLink($link)
    {
        $tx = $this->texy;

        if ($link == NULL) return NULL;

        if ($link{0} === '[') { // [ref]
            $link = substr($link, 1, -1);
            $ref = $tx->linkModule->getReference($link);
            if ($ref) {
                return Texy::completeURL($ref['URL'], $tx->linkModule->root);
            } else {
                return Texy::completeURL($link, $tx->linkModule->root);
            }
        } else { // direct URL
            return Texy::completeURL($link, $tx->linkModule->root);
        }
    }


} // TexyQuoteModule
