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
 * PARAGRAPH / GENERIC MODULE CLASS
 */
class TexyGenericBlockModule extends TexyModule {
    var $mergeMode = true;


    /**
     * Module initialization
     */
    function init()
    {
        $this->texy->genericBlock = array(&$this, 'processBlock');
    }



    function processBlock(&$blockParser, $content)
    {
        $str_blocks = $this->mergeMode
                      ? preg_split('#(\n{2,})#', $content)
                      : preg_split('#(\n(?! )|\n{2,})#', $content);

        foreach ($str_blocks as $str) {
            $str = trim($str);
            if ($str == '') continue;
            $this->processSingleBlock($blockParser, $str);
        }
    }



    /**
     * Callback function (for blocks)
     *
     *            ....  .(title)[class]{style}>
     *             ...
     *             ...
     *
     */
    function processSingleBlock(&$blockParser, $content)
    {
        preg_match($this->texy->translatePattern('#^(.*)<MODIFIER_H>?(\n.*)?()$#sU'), $content, $matches);
        list($match, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mContent2) = $matches;
        //    [1] => ...
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => >


        // ....
        //  ...  => \n
        $mContent = trim($mContent . $mContent2);
        if ($this->texy->mergeLines) {
           $mContent = preg_replace('#\n (\S)#', " \r\\1", $mContent);
           $mContent = strtr($mContent, "\n\r", " \n");
        }

        $el = &new TexyGenericBlockElement($this->texy);
        $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $el->parse($mContent);
        $blockParser->element->appendChild($el);

        // specify tag
        if ($el->contentType == TEXY_CONTENT_TEXTUAL) $el->tag = 'p';
        elseif ($mMod1 || $mMod2 || $mMod3 || $mMod4) $el->tag = 'div';
        elseif ($el->contentType == TEXY_CONTENT_BLOCK) $el->tag = '';
        else $el->tag = 'div';

        // add <br />
        if ($el->tag && (strpos($el->content, "\n") !== false)) {
            $elBr = &new TexyLineBreakElement($this->texy);
            $el->content = strtr($el->content,
                              array("\n" => $el->appendChild($elBr))
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