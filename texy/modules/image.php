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
 * IMAGES MODULE CLASS
 */
class TexyImageModule extends TexyModule
{
    /** @var callback    Callback that will be called with newly created element */
    public $handler;

    // options
    public $root       = 'images/';     // root of relative images (http)
    public $linkedRoot = 'images/';     // root of linked images (http)
    public $rootPrefix = '';            // physical location on server
    public $leftClass  = NULL;          // left-floated image class
    public $rightClass = NULL;          // right-floated image class
    public $defaultAlt = '';            // default image alternative text





    public function __construct($texy)
    {
        parent::__construct($texy);
        $this->texy->allowed['Image.normal'] = TRUE;

        if (isset($_SERVER['SCRIPT_NAME'])) {
            $this->rootPrefix = dirname($_SERVER['SCRIPT_NAME']).'/'; // physical location on server
        }
    }


    /**
     * Module initialization.
     */
    public function init()
    {
        // [*image*]:LINK    where LINK is:   url | [ref] | [*image*]
        $this->texy->registerLinePattern($this, 'processLine',     '#'.TEXY_IMAGE.TEXY_LINK_N.'??()#U');
    }







    /**
     * Add new named image
     */
    public function addReference($name, $obj)
    {
        $this->texy->addReference('*'.$name.'*', $obj);
    }




    /**
     * Receive new named link. If not exists, try
     * call user function to create one.
     */
    public function getReference($name)
    {
        $el = $this->texy->getReference('*'.$name.'*');
        if ($el instanceof TexyImageReference) return $el;
        else return FALSE;
    }




    /**
     * Preprocessing
     */
    public function preProcess($text)
    {
        // [*image*]: urls .(title)[class]{style}
        return preg_replace_callback('#^\[\*([^\n]+)\*\]:\ +(.+)\ *'.TEXY_MODIFIER.'?()$#mU', array($this, 'processReferenceDefinition'), $text);
    }



    /**
     * Callback function: [*image*]: urls .(title)[class]{style}
     * @return string
     */
    public function processReferenceDefinition($matches)
    {
        list(, $mRef, $mURLs, $mMod1, $mMod2, $mMod3) = $matches;
        //    [1] => [* (reference) *]
        //    [2] => urls
        //    [3] => (title)
        //    [4] => [class]
        //    [5] => {style}

        $elRef = new TexyImageReference($this->texy, $mURLs);
        $elRef->modifier->setProperties($mMod1, $mMod2, $mMod3);

        $this->addReference($mRef, $elRef);
        return '';
    }






    /**
     * Callback function: [* small.jpg | small-over.jpg | big.jpg .(alternative text)[class]{style}>]:LINK
     * @return string
     */
    public function processLine($parser, $matches)
    {
        if (!$this->texy->allowed['Image.normal']) return '';

        list(, $mURLs, $mMod1, $mMod2, $mMod3, $mMod4, $mLink) = $matches;
        //    [1] => URLs
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => >
        //    [6] => url | [ref] | [*image*]

        //foreach ($this->handlers as $handler)
        //    if (call_user_func_array($this->handler, array($elImage)) === FALSE) return '';

        $elImage = new TexyImageElement($this->texy);
        $elImage->setImagesRaw($mURLs);
        $elImage->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        //$elImage->setImagesRaw($mURLs);

        $keyImage = $this->texy->hash($elImage->toHtml(), TexyDomElement::CONTENT_NONE); // !!!

        if ($mLink) {
            $elLink = new TexyLinkElement($this->texy);
            if ($mLink == ':') {
                $elImage->requireLinkImage();
                $elLink->link->copyFrom($elImage->linkImage);
            } else {
                $elLink->setLinkRaw($mLink);
            }

            $keyOpen  = $this->texy->hash($elLink->opening(), TexyDomElement::CONTENT_NONE);
            $keyClose = $this->texy->hash($elLink->closing(), TexyDomElement::CONTENT_NONE);
            return $keyOpen . $keyImage . $keyClose;
        }

        if ($this->handler)
            if (call_user_func_array($this->handler, array($elImage)) === FALSE) return '';

        return $keyImage;
    }





} // TexyImageModule









class TexyImageReference {
    public $URLs;
    public $modifier;


    // constructor
    public function __construct($texy, $URLs = NULL)
    {
        $this->modifier = new TexyModifier($texy);
        $this->URLs = $URLs;
    }

}







/**
 * HTML ELEMENT IMAGE
 */
class TexyImageElement extends TexyTextualElement
{
    public $image;
    public $overImage;
    public $linkImage;

    public $width, $height;


    // constructor
    public function __construct($texy)
    {
        parent::__construct($texy);
        $this->image = new TexyUrl($texy);
        $this->overImage = new TexyUrl($texy);
        $this->linkImage = new TexyUrl($texy);
    }



    public function setImages($URL = NULL, $URL_over = NULL, $URL_link = NULL)
    {
        $this->image->set($URL, $this->texy->imageModule->root, TRUE);
        $this->overImage->set($URL_over, $this->texy->imageModule->root, TRUE);
        $this->linkImage->set($URL_link, $this->texy->imageModule->linkedRoot, TRUE);
    }



    public function setSize($width, $height)
    {
        $this->width = abs((int) $width);
        $this->height = abs((int) $height);
    }


    public function setImagesRaw($URLs)
    {

        $elRef = $this->texy->imageModule->getReference(trim($URLs));
        if ($elRef) {
            $URLs = $elRef->URLs;
            $this->modifier->copyFrom($elRef->modifier);
        }

        $URLs = explode('|', $URLs . '||');

        // dimensions
        if (preg_match('#^(.*) (?:(\d+)|\?) *x *(?:(\d+)|\?) *()$#U', $URLs[0], $matches)) {
            $URLs[0] = $matches[1];
            $this->setSize($matches[2], $matches[3]);
        }

        $this->setImages($URLs[0], $URLs[1], $URLs[2]);
    }




    protected function generateTags(&$tags)
    {
        if ($this->image->asURL() == '') return;  // image URL is required

        // classes & styles
        $attrs = $this->modifier->getAttrs('img');
        $attrs['class'] = $this->modifier->classes;
        $attrs['style'] = $this->modifier->styles;
        $attrs['id'] = $this->modifier->id;

        if ($this->modifier->hAlign == TexyModifier::HALIGN_LEFT) {
            if ($this->texy->imageModule->leftClass != '')
                $attrs['class'][] = $this->texy->imageModule->leftClass;
            else
                $attrs['style']['float'] = 'left';

        } elseif ($this->modifier->hAlign == TexyModifier::HALIGN_RIGHT)  {

            if ($this->texy->imageModule->rightClass != '')
                $attrs['class'][] = $this->texy->imageModule->rightClass;
            else
                $attrs['style']['float'] = 'right';
        }

        if ($this->modifier->vAlign)
            $attrs['style']['vertical-align'] = $this->modifier->vAlign;

        // width x height generate
        $this->requireSize();
        if ($this->width) $attrs['width'] = $this->width;
        if ($this->height) $attrs['height'] = $this->height;

        // attribute generate
        $this->texy->summary['images'][] = $attrs['src'] = $this->image->asURL();

        // onmouseover actions generate
        if ($this->overImage->asURL()) {
            $attrs['onmouseover'] = 'this.src=\''.$this->overImage->asURL().'\'';
            $attrs['onmouseout'] = 'this.src=\''.$this->image->asURL().'\'';
            $this->texy->summary['preload'][] = $this->overImage->asURL();
        }

        // alternative text generate
        $attrs['alt'] = $this->modifier->title != NULL ? $this->modifier->title : $this->texy->imageModule->defaultAlt;

        $tags['img'] = $attrs;
    }



    protected function requireSize()
    {
        if ($this->width) return TRUE;

        $file = $this->texy->imageModule->rootPrefix . '/' . $this->image->asURL();
        if (!is_file($file)) return FALSE;

        $size = getImageSize($file);
        if (!is_array($size)) return FALSE;

        $this->setSize($size[0], $size[1]);
        return TRUE;
    }


    public function requireLinkImage()
    {
        if ($this->linkImage->asURL() == '')
            $this->linkImage->set($this->image->value, $this->texy->imageModule->linkedRoot, TRUE);

    }



} // TexyImages