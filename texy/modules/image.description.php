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
 * IMAGE WITH DESCRIPTION MODULE CLASS
 */
class TexyImageDescModule extends TexyModule
{
    /** @var callback    Callback that will be called with newly created element */
    public $handler;

    public $boxClass   = 'image';        // non-floated box class
    public $leftClass  = 'image left';   // left-floated box class
    public $rightClass = 'image right';  // right-floated box class


    public function __construct($texy)
    {
        parent::__construct($texy);
        $this->texy->allowed['Image.desc'] = TRUE;
    }


    /**
     * Module initialization.
     */
    public function init()
    {
        if ($this->texy->allowed['Image.desc'])
            $this->texy->registerBlockPattern(
                $this,
                'processBlock',
                '#^'.TEXY_IMAGE.TEXY_LINK_N.'?? +\*\*\* +(.*)'.TEXY_MODIFIER_H.'?()$#mU'
            );
    }



    /**
     * Callback function (for blocks)
     *
     *            [*image*]:link *** .... .(title)[class]{style}>
     *
     */
    public function processBlock($parser, $matches)
    {
        list(, $mURLs, $mImgMod1, $mImgMod2, $mImgMod3, $mImgMod4, $mLink, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
        //    [1] => URLs
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => >
        //    [6] => url | [ref] | [*image*]
        //    [7] => ...
        //    [8] => (title)
        //    [9] => [class]
        //    [10] => {style}
        //    [11] => >

        $el = new TexyImageDescElement($this->texy);
        $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

        $elImage = new TexyImageElement($this->texy);
        $elImage->setImagesRaw($mURLs);
        $elImage->modifier->setProperties($mImgMod1, $mImgMod2, $mImgMod3, $mImgMod4);
        //$elImage->setImagesRaw($mURLs);

        $el->modifier->hAlign = $elImage->modifier->hAlign;
        $elImage->modifier->hAlign = NULL;

        $content = $this->texy->hash($elImage->__toString(), Texy::CONTENT_NONE); // !!!

        if ($mLink) {
            $elLink = new TexyLinkElement($this->texy);
            if ($mLink === ':') {
                $elImage->requireLinkImage();
                if ($elImage->linkImage) $elLink->link->copyFrom($elImage->linkImage);
            } else {
                $elLink->setLinkRaw($mLink);
            }

            $keyOpen  = $this->texy->hash($elLink->opening(), Texy::CONTENT_NONE);
            $keyClose = $this->texy->hash($elLink->closing(), Texy::CONTENT_NONE);
            $content = $keyOpen . $content . $keyClose;
        }
        $elImg = new TexyTextualElement($this->texy);
        $elImg->content = $content;
        $el->appendChild($elImg);

        $elDesc = new TexyGenericBlockElement($this->texy);
        $elDesc->parse(ltrim($mContent));
        $el->appendChild($elDesc);

        if ($this->handler)
            if (call_user_func_array($this->handler, array($el)) === FALSE) return;

        $parser->element->appendChild($el);
    }




} // TexyImageModule














/**
 * HTML ELEMENT IMAGE (WITH DESCRIPTION)
 */
class TexyImageDescElement extends TexyBlockElement
{



    protected function generateTags(&$tags)
    {
        $el = TexyHtml::el('div');
        $tags['div'] = $el;

        foreach ($this->modifier->getAttrs('div') as $attr => $val) $el->$attr = $val;

        $el->id = $this->modifier->id;
        $el->class = $this->modifier->classes;
        $el->style = $this->modifier->styles;

        if ($this->modifier->hAlign === TexyModifier::HALIGN_LEFT) {
            $el->class[] = $this->texy->imageDescModule->leftClass;

        } elseif ($this->modifier->hAlign === TexyModifier::HALIGN_RIGHT)  {
            $el->class[] = $this->texy->imageDescModule->rightClass;

        } elseif ($this->texy->imageDescModule->boxClass)
            $el->class[] = $this->texy->imageDescModule->boxClass;

    }



}  // TexyImageDescElement
