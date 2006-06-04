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
 * @version    1.2 for PHP4 & PHP5 (released 2006/06/01)
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();




// images   [* urls .(title)[class]{style} >]
define('TEXY_PATTERN_IMAGE',    '\[\*([^\n'.TEXY_HASH.']+)'.TEXY_PATTERN_MODIFIER.'? *(\*|>|<)\]');


/**
 * IMAGES MODULE CLASS
 */
class TexyImageModule extends TexyModule
{
    /** @var callback    Callback that will be called with newly created element */
    var $handler;

    // options
    var $root       = 'images/';     // root of relative images (http)
    var $linkedRoot = 'images/';     // root of linked images (http)
    var $rootPrefix = '';            // physical location on server
    var $leftClass  = NULL;          // left-floated image class
    var $rightClass = NULL;          // right-floated image class
    var $defaultAlt = '';            // default image alternative text





    function __construct(&$texy)
    {
        parent::__construct($texy);

//    $this->rootPrefix = dirname($_SERVER['SCRIPT_NAME']); // physical location on server
    }


    /**
     * Module initialization.
     */
    function init()
    {
        Texy::adjustDir($this->root);
        Texy::adjustDir($this->linkedRoot);
        Texy::adjustDir($this->rootPrefix);

        // [*image*]:LINK    where LINK is:   url | [ref] | [*image*]
        $this->texy->registerLinePattern($this, 'processLine',     '#'.TEXY_PATTERN_IMAGE.TEXY_PATTERN_LINK_N.'??()#U');
    }







    /**
     * Preprocessing
     */
    function preProcess(&$text)
    {
        // [*image*]: urls .(title)[class]{style}
        $text = preg_replace_callback('#^\[\*([^\n]+)\*\]:\ +(.+)\ *'.TEXY_PATTERN_MODIFIER.'?()$#mU', array(&$this, 'processReferenceDefinition'), $text);
    }



    /**
     * Callback function: [*image*]: urls .(title)[class]{style}
     * @return string
     */
    function processReferenceDefinition($matches)
    {
        list(, $mRef, $mURLs, $mMod1, $mMod2, $mMod3) = $matches;
        //    [1] => [* (reference) *]
        //    [2] => urls
        //    [3] => (title)
        //    [4] => [class]
        //    [5] => {style}

/*
        $elRef = &new TexyImageReference($this->texy, $mURLs);
        $elRef->modifier->setProperties($mMod1, $mMod2, $mMod3);

        $this->texy->addReference($mRef, $elRef);
*/
        return '';
    }






    /**
     * Callback function: [* small.jpg | small-over.jpg | big.jpg .(alternative text)[class]{style}>]:LINK
     * @return string
     */
    function processLine(&$parser, $matches)
    {
        if (!$this->allowed) return '';

        list(, $mURLs, $mMod1, $mMod2, $mMod3, $mMod4, $mLink) = $matches;
        //    [1] => URLs
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => >
        //    [6] => url | [ref] | [*image*]


//        $elImage = &$this->handleReference($mURLs,

        $elImage = &new TexyImageElement($this->texy);
        $elImage->setImagesRaw($mURLs);
        $elImage->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

        if ($mLink) {
            $elLink = &new TexyLinkElement($this->texy);
            if ($mLink == ':') {
                $elImage->requireLinkImage();
                $elLink->link->copyFrom($elImage->linkImage);
            } else {
                $elLink->setLinkRaw($mLink);
            }

            return $parser->element->appendChild(
                $elLink,
                $parser->element->appendChild($elImage)
            );
        }

        if ($this->handler)
            if (call_user_func_array($this->handler, array(&$elImage)) === FALSE) return '';

        return $parser->element->appendChild($elImage);
    }





} // TexyImageModule













/**
 * HTML ELEMENT IMAGE
 */
class TexyImageElement extends TexyHTMLElement
{
    var $image;
    var $overImage;
    var $linkImage;

    var $width, $height;


    // constructor
    function __construct(&$texy)
    {
        parent::__construct($texy);
        $this->image = &new TexyURL($texy);
        $this->overImage = &new TexyURL($texy);
        $this->linkImage = &new TexyURL($texy);
    }



    function setImages($URL = NULL, $URL_over = NULL, $URL_link = NULL)
    {
        $this->image->set($URL, $this->texy->imageModule->root, TRUE);
        $this->overImage->set($URL_over, $this->texy->imageModule->root, TRUE);
        $this->linkImage->set($URL_link, $this->texy->imageModule->linkedRoot, TRUE);
    }



    function setSize($width, $height)
    {
        $this->width = abs((int) $width);
        $this->height = abs((int) $height);
    }


    // private
    function setImagesRaw($URLs)
    {
/*
        $elRef = &$this->texy->imageModule->getReference(trim($URLs));
        if ($elRef) {
            $URLs = $elRef->URLs;
            $this->modifier->copyFrom($elRef->modifier);
        }
*/
        $URLs = explode('|', $URLs . '||');

        // dimensions
        if (preg_match('#^(.*) (?:(\d+)|\?) *x *(?:(\d+)|\?) *()$#U', $URLs[0], $matches)) {
            $URLs[0] = $matches[1];
            $this->setSize($matches[2], $matches[3]);
        }

        $this->setImages($URLs[0], $URLs[1], $URLs[2]);
    }




    function generateTags(&$tags)
    {
        if ($this->image->asURL() == '') return;  // image URL is required

        // classes & styles
        $attrs = $this->modifier->getAttrs('img');
        $attrs['class'] = $this->modifier->classes;
        $attrs['style'] = $this->modifier->styles;
        $attrs['id'] = $this->modifier->id;

        if ($this->modifier->hAlign == TEXY_HALIGN_LEFT) {
            if ($this->texy->imageModule->leftClass != '')
                $attrs['class'][] = $this->texy->imageModule->leftClass;
            else
                $attrs['style']['float'] = 'left';

        } elseif ($this->modifier->hAlign == TEXY_HALIGN_RIGHT)  {

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
        $this->texy->summary->images[] = $attrs['src'] = $this->image->asURL();

        // onmouseover actions generate
        if ($this->overImage->asURL()) {
            $attrs['onmouseover'] = 'this.src=\''.$this->overImage->asURL().'\'';
            $attrs['onmouseout'] = 'this.src=\''.$this->image->asURL().'\'';
            $this->texy->summary->preload[] = $this->overImage->asURL();
        }

        // alternative text generate
        $attrs['alt'] = $this->modifier->title ? $this->modifier->title : $this->texy->imageModule->defaultAlt;

        $tags['img'] = $attrs;
    }



    function requireSize()
    {
        if ($this->width) return;

        $file = $this->texy->imageModule->rootPrefix . $this->image->asURL();
        if (!is_file($file)) return FALSE;

        $size = getImageSize($file);
        if (!is_array($size)) return FALSE;

        $this->setSize($size[0], $size[1]);
    }


    function requireLinkImage()
    {
        if ($this->linkImage->asURL() == '')
            $this->linkImage->set($this->image->value, $this->texy->imageModule->linkedRoot, TRUE);

    }



} // TexyImages







?>