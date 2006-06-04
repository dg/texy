<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://www.texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 * @package    Texy
 * @category   Text
 * @version    1.0 for PHP4 & PHP5 (released 2006/04/18)
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * QUOTE & BLOCKQUOTE MODULE CLASS
 */
class TexyQuoteModule extends TexyModule {
    var $allowed;



    // constructor
    function TexyQuoteModule(&$texy)
    {
        parent::__construct($texy);

        $this->allowed->line  = true;
        $this->allowed->block = true;
    }



    /**
     * Module initialization.
     */
    function init()
    {
        if ($this->allowed->block)
            $this->registerBlockPattern('processBlock', '#^(?:<MODIFIER_H>\n)?>(\ +|:)(\S.*)$#mU');

        if ($this->allowed->line)
            $this->registerLinePattern('processLine', '#(?<!\>)(\>\>)(?!\ |\>)(.+)<MODIFIER>?(?<!\ |\<)\<\<(?!\<)<LINK>??()#U', 'q');
    }


    /**
     * Callback function: >>.... .(title)[class]{style}<<:LINK
     * @return string
     */
    function processLine(&$lineParser, &$matches, $tag)
    {
        list($match, $mMark, $mContent, $mMod1, $mMod2, $mMod3, $mLink) = $matches;
        //    [1] => **
        //    [2] => ...
        //    [3] => (title)
        //    [4] => [class]
        //    [5] => {style}
        //    [6] => LINK

        $texy = & $this->texy;
        $el = &new TexyQuoteElement($texy);
        $el->modifier->setProperties($mMod1, $mMod2, $mMod3);

        if ($mLink)
            $el->cite->set($mLink);

        return $lineParser->element->appendChild($el, $mContent);
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
    function processBlock(&$blockParser, &$matches)
    {
        list($match, $mMod1, $mMod2, $mMod3, $mMod4, $mSpaces, $mContent) = $matches;
        //    [1] => (title)
        //    [2] => [class]
        //    [3] => {style}
        //    [4] => <>
        //    [5] => spaces |
        //    [6] => ... / LINK

        $texy = & $this->texy;
        $el = &new TexyBlockQuoteElement($texy);
        $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $blockParser->element->appendChild($el);

        $content = '';
        $linkTarget = '';
        $spaces = '';
        do {
            if ($mSpaces == ':') $linkTarget = trim($mContent);
            else {
                if ($spaces === '') $spaces = max(1, strlen($mSpaces));
                $content .= $mContent . TEXY_NEWLINE;
            }

            if (!$blockParser->receiveNext("#^>(?:|(\ {1,$spaces}|:)(.*))()$#mA", $matches)) break;
            list($match, $mSpaces, $mContent) = $matches;
        } while (true);

        if ($linkTarget) {                                  // !!!!!
            $elx = &new TexyLinkElement($this->texy);
            $elx->setLinkRaw($linkTarget);
            $el->cite->set($elx->link->URL);
        }

        $el->parse($content);
    }



} // TexyQuoteModule





/****************************************************************************
                                                             TEXY! DOM ELEMENTS                          */




/**
 * HTML ELEMENT BLOCKQUOTE
 */
class TexyBlockQuoteElement extends TexyBlockElement {
    var $cite;


    function __construct(&$texy)
    {
        parent::__construct($texy);
        $this->cite = & $texy->createURL();
    }


    function generateTags(&$tags)
    {
        parent::generateTags($tags, 'blockquote');
        $tags['blockquote']['cite'] = $this->cite->URL;
    }

} // TexyBlockQuoteElement





/**
 * HTML TAG QUOTE
 */
class TexyQuoteElement extends TexyInlineTagElement {
    var $cite;


    function __construct(&$texy)
    {
        parent::__construct($texy);
        $this->cite = & $texy->createURL();
    }


    function generateTags(&$tags)
    {
        parent::generateTags($tags, 'q');
        $tags['q']['cite'] = $this->cite->URL;
    }


} // TexyBlockQuoteElement






?>