<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
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
    /** @var callback    Callback that will be called with newly created element */
    public $handler;

    public $allowed;



    public function __construct($texy)
    {
        parent::__construct($texy);

        $this->allowed = (object) NULL;
        $this->allowed->line  = TRUE;
        $this->allowed->block = TRUE;
    }



    /**
     * Module initialization.
     */
    public function init()
    {
        if ($this->allowed->block)
            $this->texy->registerBlockPattern(
                $this,
                'processBlock',
                '#^(?:<MODIFIER_H>\n)?\>(\ +|:)(\S.*)$#mU'
            );

        if ($this->allowed->line)
            $this->texy->registerLinePattern(
                $this,
                'processLine',
                '#(?<!\>)(\>\>)(?!\ |\>)(.+)<MODIFIER>?(?<!\ |\<)\<\<(?!\<)<LINK>??()#U', 'q'
            );
    }


    /**
     * Callback function: >>.... .(title)[class]{style}<<:LINK
     * @return string
     */
    public function processLine($parser, $matches, $tag)
    {
        list(, $mMark, $mContent, $mMod1, $mMod2, $mMod3, $mLink) = $matches;
        //    [1] => **
        //    [2] => ...
        //    [3] => (title)
        //    [4] => [class]
        //    [5] => {style}
        //    [6] => LINK

        $texy =  $this->texy;
        $el = new TexyQuoteElement($texy);
        $el->modifier->setProperties($mMod1, $mMod2, $mMod3);

        if ($mLink)
            $el->cite->set($mLink);

        if ($this->handler)
            if (call_user_func_array($this->handler, array($el)) === FALSE) return '';

        return $parser->element->appendChild($el, $mContent);
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

        $texy = $this->texy;
        $el = new TexyBlockQuoteElement($texy);
        $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

        $content = '';
        $linkTarget = '';
        $spaces = '';
        do {
            if ($mSpaces == ':') $linkTarget = trim($mContent);
            else {
                if ($spaces === '') $spaces = max(1, strlen($mSpaces));
                $content .= $mContent . TEXY_NEWLINE;
            }

            if (!$parser->receiveNext("#^>(?:|(\ {1,$spaces}|:)(.*))()$#mA", $matches)) break;
            list(, $mSpaces, $mContent) = $matches;
        } while (TRUE);

        if ($linkTarget) {                                  // !!!!!
            $elx = new TexyLinkElement($this->texy);
            $elx->setLinkRaw($linkTarget);
            $el->cite->set($elx->link->asURL());
        }

        $el->parse($content);

        if ($this->handler)
            if (call_user_func_array($this->handler, array($el)) === FALSE) return;

        $parser->element->appendChild($el);
    }



} // TexyQuoteModule








/**
 * HTML ELEMENT BLOCKQUOTE
 */
class TexyBlockQuoteElement extends TexyBlockElement
{
    public $tag = 'blockquote';
    public $cite;


    public function __construct($texy)
    {
        parent::__construct($texy);
        $this->cite = new TexyUrl($texy);
    }


    protected function generateTags(&$tags)
    {
        parent::generateTags($tags);
        $tags[$this->tag]['cite'] = $this->cite->asURL();
    }

} // TexyBlockQuoteElement





/**
 * HTML TAG QUOTE
 */
class TexyQuoteElement extends TexyInlineTagElement
{
    public $tag = 'q';
    public $cite;


    public function __construct($texy)
    {
        parent::__construct($texy);
        $this->cite = new TexyUrl($texy);
    }


    protected function generateTags(&$tags)
    {
        parent::generateTags($tags);
        $tags[$this->tag]['cite'] = $this->cite->asURL();
    }


} // TexyBlockQuoteElement
