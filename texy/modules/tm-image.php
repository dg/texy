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
 * @version    1.1 for PHP4 & PHP5 $Date$ $Revision$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();




// images   [* urls .(title)[class]{style} >]
define('TEXY_PATTERN_IMAGE',    '\[\*([^\n'.TEXY_HASH.']+)'.TEXY_PATTERN_MODIFIER.'? *(\*|>|<)\]');


/**
 * IMAGES MODULE CLASS
 */
class TexyImageModule extends TexyModule {
    // options
    var $root       = 'images/';     // root of relative images (http)
    var $linkedRoot = 'images/';     // root of linked images (http)
    var $rootPrefix = '';            // physical location on server
    var $leftClass  = null;          // left-floated image class
    var $rightClass = null;          // right-floated image class
    var $defaultAlt = '';            // default image alternative text





    // constructor
    function TexyImageModule(&$texy)
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
        $this->registerLinePattern('processLine',     '#'.TEXY_PATTERN_IMAGE.TEXY_PATTERN_LINK_N.'??()#U');
    }




    /**
     * Add new named image
     */
    function addReference($name, &$obj)
    {
        $this->texy->addReference('*'.$name.'*', $obj);
    }




    /**
     * Receive new named link. If not exists, try
     * call user function to create one.
     */
    function &getReference($name)
    {
        $el = $this->texy->getReference('*'.$name.'*');
        if (is_a($el, 'TexyImageReference')) return $el;
        else {
          $false = false; // php4_sucks
          return $false;
        }
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
    function processReferenceDefinition(&$matches)
    {
        list($match, $mRef, $mUrls, $mMod1, $mMod2, $mMod3) = $matches;
        //    [1] => [* (reference) *]
        //    [2] => urls
        //    [3] => (title)
        //    [4] => [class]
        //    [5] => {style}

        $elRef = &new TexyImageReference($this->texy, $mUrls);
        $elRef->modifier->setProperties($mMod1, $mMod2, $mMod3);

        $this->addReference($mRef, $elRef);

        return '';
    }






    /**
     * Callback function: [* texy.gif *]: small.jpg | small-over.jpg | big.jpg .(alternative text)[class]{style}>]:LINK
     * @return string
     */
    function processLine(&$lineParser, &$matches)
    {
        if (!$this->allowed) return '';
        list($match, $mURLs, $mMod1, $mMod2, $mMod3, $mMod4, $mLink) = $matches;
        //    [1] => URLs
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => >
        //    [6] => url | [ref] | [*image*]

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

            return $lineParser->element->appendChild(
                $elLink,
                $lineParser->element->appendChild($elImage)
            );
        }

        return $lineParser->element->appendChild($elImage);
    }





} // TexyImageModule






class TexyImageReference {
    var $URLs;
    var $modifier;


    // constructor
    function TexyImageReference(&$texy, $URLs = null)
    {
        $this->modifier = & $texy->createModifier();
        $this->URLs = $URLs;
    }

}






/***************************************************************************
                                                             TEXY! DOM ELEMENTS                          */






/**
 * HTML ELEMENT IMAGE
 */
class TexyImageElement extends TexyHTMLElement {
    var $parentModule;

    var $image;
    var $overImage;
    var $linkImage;

    var $width, $height;


    // constructor
    function __construct(&$texy)
    {
        parent::__construct($texy);
        $this->parentModule = & $texy->imageModule;

        $this->image = & $texy->createURL();
        $this->image->root = $this->parentModule->root;

        $this->overImage = & $texy->createURL();
        $this->overImage->root = $this->parentModule->root;

        $this->linkImage = & $texy->createURL();
        $this->linkImage->root = $this->parentModule->linkedRoot;
    }



    function setImages($URL = null, $URL_over = null, $URL_link = null)
    {
        if ($URL)
            $this->image->set($URL, TEXY_URL_IMAGE_INLINE);
        else
            $this->image->clear();

        if ($URL_over)
            $this->overImage->set($URL_over, TEXY_URL_IMAGE_INLINE);
        else
            $this->overImage->clear();

        if ($URL_link)
            $this->linkImage->set($URL_link, TEXY_URL_IMAGE_LINKED);
        else
            $this->linkImage->clear();
    }



    function setSize($width, $height)
    {
        $this->width = abs((int) $width);
        $this->height = abs((int) $height);
    }


    // private
    function setImagesRaw($URLs)
    {
        $elRef = &$this->parentModule->getReference(trim($URLs));
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




    function generateTags(&$tags)
    {
        if ($this->image->URL == null) return;  // image URL is required

        // classes & styles
        $attrs = $this->modifier->getAttrs('img');
        $attrs['class'] = $this->modifier->classes;
        $attrs['style'] = $this->modifier->styles;
        $attrs['id'] = $this->modifier->id;

        if ($this->modifier->hAlign == TEXY_HALIGN_LEFT) {
            if ($this->parentModule->leftClass != '')
                $attrs['class'][] = $this->parentModule->leftClass;
            else
                $attrs['style']['float'] = 'left';

        } elseif ($this->modifier->hAlign == TEXY_HALIGN_RIGHT)  {

            if ($this->parentModule->rightClass != '')
                $attrs['class'][] = $this->parentModule->rightClass;
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
        $this->texy->summary->images[] = $attrs['src'] = $this->image->URL;

        // onmouseover actions generate
        if ($this->overImage->URL) {
            $attrs['onmouseover'] = 'this.src=\''.$this->overImage->URL.'\'';
            $attrs['onmouseout'] = 'this.src=\''.$this->image->URL.'\'';
            $this->texy->summary->preload[] = $this->overImage->URL;
        }

        // alternative text generate
        $attrs['alt'] = $this->modifier->title ? $this->modifier->title : $this->parentModule->defaultAlt;

        $tags['img'] = $attrs;
    }



    function requireSize()
    {
        if ($this->width) return;

        $file = $this->parentModule->rootPrefix . $this->image->URL;
        if (!is_file($file)) return false;

        $size = getImageSize($file);
        if (!is_array($size)) return false;

        $this->setSize($size[0], $size[1]);
    }


    function requireLinkImage()
    {
        if ($this->linkImage->URL == null)
            $this->linkImage->set($this->image->text, TEXY_URL_IMAGE_LINKED);
    }



} // TexyImages







?>