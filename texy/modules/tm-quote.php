<?php

/**
 * -------------------------------------
 *   BLOCKQUOTE - TEXY! DEFAULT MODULE
 * -------------------------------------
 *
 * Version 1 Release Candidate
 *
 * Copyright (c) 2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * For the full copyright and license information, please view the COPYRIGHT
 * file that was distributed with this source code. If the COPYRIGHT file is
 * missing, please visit the Texy! homepage: http://www.texy.info
 *
 * @package Texy
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
        parent::TexyModule($texy);

        $this->allowed->line  = true;
        $this->allowed->block = true;
    }



    /***
     * Module initialization.
     */
    function init()
    {
        if ($this->allowed->block)
            $this->registerBlockPattern('processBlock', '#^(?:MODIFIER_H\n)?>(\ +|:)(\S.*)$#mU');

        if ($this->allowed->line)
            $this->registerLinePattern('processLine', '#(?<!\>)(\>\>)(?!\ |\>)(.+)MODIFIER?(?<!\ |\<)\<\<(?!\<)LINK??()#U', 'q');
    }


    /***
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

        return $el->addTo($lineParser->element, $mContent);
    }




    /***
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
        $blockParser->addChildren($el);

        $content = '';
        $linkTarget = '';
        $spaces = '';
        do {
            if ($mSpaces == ':') $linkTarget = trim($mContent);
            else {
                if ($spaces === '') $spaces = strlen($mSpaces);
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


    function TexyBlockQuoteElement(&$texy)
    {
        parent::TexyBlockElement($texy);
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


    function TexyQuoteElement(&$texy)
    {
        parent::TexyInlineTagElement($texy);
        $this->cite = & $texy->createURL();
    }


    function generateTags(&$tags)
    {
        parent::generateTags($tags, 'q');
        $tags['q']['cite'] = $this->cite->URL;
    }


} // TexyBlockQuoteElement






?>