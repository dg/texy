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
    /** @var callback    Callback that will be called with newly created element */
    public $handler;




    public function __construct($texy)
    {
        parent::__construct($texy);

        
        $allowed = & $this->texy->allowed;
        $allowed['Quote.line']  = TRUE;
        $allowed['Quote.block'] = TRUE;
    }



    /**
     * Module initialization.
     */
    public function init()
    {
        if ($this->texy->allowed['Quote.block'])
            $this->texy->registerBlockPattern(
                $this,
                'processBlock',
                '#^(?:'.TEXY_MODIFIER_H.'\n)?\>(\ +|:)(\S.*)$#mU'
            );

        if ($this->texy->allowed['Quote.line'])
            $this->texy->registerLinePattern(
                $this,
                'processLine',
                '#(?<!\>)(\>\>)(?!\ |\>)(.+)'.TEXY_MODIFIER.'?(?<!\ |\<)\<\<(?!\<)'.TEXY_LINK.'??()#U'
            );
    }


    /**
     * Callback function: >>.... .(title)[class]{style}<<:LINK
     * @return string
     */
    public function processLine($parser, $matches)
    {
        list(, , $mContent, $mMod1, $mMod2, $mMod3, $mLink) = $matches;
        //    [1] => **
        //    [2] => ...
        //    [3] => (title)
        //    [4] => [class]
        //    [5] => {style}
        //    [6] => LINK

        $texy =  $this->texy;

        $el = NHtml::el('q');
        $modifier = new TexyModifier($this->texy);
        $modifier->setProperties($mMod1, $mMod2, $mMod3);
        $modifier->decorate($el);

        if ($mLink) {
            $cite = new TexyUrl($texy);
            $cite->set($mLink);
            $el->cite = $cite->asURL();
        }

        if ($this->handler)
            if (call_user_func_array($this->handler, array($el)) === FALSE) return '';

        $keyOpen  = $this->texy->hash($el->startTag(), TexyDomElement::CONTENT_NONE);
        $keyClose = $this->texy->hash($el->endTag(), TexyDomElement::CONTENT_NONE);
        return $keyOpen . $mContent . $keyClose;
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
                $content .= $mContent . "\n";
            }

            if (!$parser->receiveNext("#^>(?:|(\\ {1,$spaces}|:)(.*))()$#mA", $matches)) break;
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




