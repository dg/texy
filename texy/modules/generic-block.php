<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://www.texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 * @package    Texy
 * @category   Text
 * @version    1.5 for PHP4 & PHP5 $Date$ $Revision$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * PARAGRAPH / GENERIC MODULE CLASS
 */
class TexyGenericBlockModule extends TexyModule
{
    /** @var callback    Callback that will be called with newly created element */
    var $handler;

    /** @var bool    ... */
    var $mergeMode = TRUE;


    /**
     * Module initialization
     */
    function init()
    {
        $this->texy->genericBlock = array(&$this, 'processBlock');
    }



    function processBlock(&$parser, $content)
    {
        $str_blocks = $this->mergeMode
                      ? preg_split('#(\n{2,})#', $content)
                      : preg_split('#(\n(?! )|\n{2,})#', $content);

        foreach ($str_blocks as $str) {
            $str = trim($str);
            if ($str == '') continue;
            $this->processSingleBlock($parser, $str);
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
    function processSingleBlock(&$parser, $content)
    {
        preg_match($this->texy->translatePattern('#^(.*)<MODIFIER_H>?(\n.*)?()$#sU'), $content, $matches);
        list(, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mContent2) = $matches;
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

        // specify tag
        if ($el->contentType == TEXY_CONTENT_TEXTUAL) $el->tag = 'p';
        elseif ($mMod1 || $mMod2 || $mMod3 || $mMod4) $el->tag = 'div';
        elseif ($el->contentType == TEXY_CONTENT_BLOCK) $el->tag = '';
        else $el->tag = 'div';

        // add <br />
        if ($el->tag && (strpos($el->content, "\n") !== FALSE)) {
            $elBr = &new TexyTextualElement($this->texy);
            $elBr->tag = 'br';
            $el->content = strtr($el->content,
                              array("\n" => $el->appendChild($elBr))
                           );
        }

        if ($this->handler)
            if (call_user_func_array($this->handler, array(&$el)) === FALSE) return;

        $parser->element->appendChild($el);
    }





} // TexyGenericBlockModule





/**
 * HTML ELEMENT PARAGRAPH / DIV / TRANSPARENT
 */
class TexyGenericBlockElement extends TexyTextualElement
{
    var $tag = 'p';


} // TexyGenericBlockElement





?>