<?php

/**
 * ----------------------------------------------
 *   PARAGRAPH / GENERIC - TEXY! DEFAULT MODULE
 * ----------------------------------------------
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
 * PARAGRAPH / GENERIC MODULE CLASS
 */
class TexyGenericBlockModule extends TexyModule {
    var $mergeLines = true;


    /***
     * Module initialization
     */
    function init()
    {
        $this->texy->genericBlock = array(&$this, 'processBlock');
    }



    function processBlock(&$blockParser, $content)
    {
        $str_blocks = $this->mergeLines
                      ? preg_split('#(\n{2,})#', $content)
                      : preg_split('#(\n(?! )|\n{2,})#', $content);

        foreach ($str_blocks as $str) {
            $str = trim($str);
            if (!$str) continue;
            $this->processSingleBlock($blockParser, $str);
        }
    }



    /***
     * Callback function (for blocks)
     *
     *            ....  .(title)[class]{style}>
     *             ...
     *             ...
     *
     */
    function processSingleBlock(&$blockParser, $content)
    {
        preg_match($this->texy->translatePattern('#^(.*)MODIFIER_H?(\n.*)?()$#sU'), $content, $matches);
        list($match, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mContent2) = $matches;
        //    [1] => ...
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => >


        // ....
        //  ...  => \n
        $mContent = preg_replace('#\n (\S)#', " \r\\1", trim($mContent . $mContent2));
        $mContent = strtr($mContent, "\n\r", " \n");

        $el = &new TexyGenericBlockElement($this->texy);
        $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $el->parse($mContent);
        $blockParser->addChildren($el);

        // specify tag
        if ($el->contentType == TEXY_CONTENT_TEXTUAL) $el->tag = 'p';
        elseif ($mMod1 || $mMod2 || $mMod3 || $mMod4) $el->tag = 'div';
        elseif ($el->contentType == TEXY_CONTENT_BLOCK) $el->tag = '';
        else $el->tag = 'div';

        // add <br />
        if ($el->tag && (strpos($el->content, "\n") !== false)) {
            $elBr = &new TexyLineBreakElement($this->texy);
            $el->content = strtr($el->content,
                              array("\n" => $elBr->addTo($el))
                           );
        }
    }





} // TexyGenericBlockModule








/****************************************************************************
                                                             TEXY! DOM ELEMENTS                          */



/**
 * HTML ELEMENT LINE BREAK
 */
class TexyLineBreakElement extends TexyTextualElement {
    var $tag = 'br';

} // TexyLineBreakElement




/**
 * HTML ELEMENT PARAGRAPH / DIV / TRANSPARENT
 */
class TexyGenericBlockElement extends TexyTextualElement {
    var $tag = 'p';


} // TexyGenericBlockElement





?>