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
 * Images module
 */
class TexyImageModule extends TexyModule
{
    protected $allow = array('Image.normal');

    public $root       = 'images/';     // root of relative images (http)
    public $linkedRoot = 'images/';     // root of linked images (http)
    public $rootPrefix = '';            // physical location on server
    public $leftClass  = NULL;          // left-floated image class
    public $rightClass = NULL;          // right-floated image class
    public $defaultAlt = '';            // default image alternative text





    public function __construct($texy)
    {
        parent::__construct($texy);

        if (isset($_SERVER['SCRIPT_NAME'])) {
            $this->rootPrefix = dirname($_SERVER['SCRIPT_NAME']) . '/'; // physical location on server
        }
    }



    public function init()
    {
        // [*image*]:LINK    where LINK is:   url | [ref] | [*image*]
        $this->texy->registerLinePattern(
            $this,
            'processLine',
            '#'.TEXY_IMAGE.TEXY_LINK_N.'??()#U',
            'Image.normal'
        );
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
        list(, $mURLs, $mMod1, $mMod2, $mMod3, $mMod4, $mLink) = $matches;
        //    [1] => URLs
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => >
        //    [6] => url | [ref] | [*image*]

        $elImage = new TexyImageElement($this->texy);

        $elRef = $this->getReference(trim($mURLs));
        if ($elRef) {
            $mURLs = $elRef->URLs;
            $elImage->modifier = clone $elRef->modifier;
        }

        $URLs = explode('|', $mURLs);

        // dimensions
        if (preg_match('#^(.*) (?:(\d+)|\?) *x *(?:(\d+)|\?) *()$#U', $URLs[0], $matches)) {
            $URLs[0] = $matches[1];
            $elImage->setSize($matches[2], $matches[3]);
        }

        $elImage->image = new TexyLink($this->texy, $URLs[0], TexyLink::IMAGE);
        if (isset($URLs[1])) $elImage->overImage = new TexyLink($this->texy, $URLs[1], TexyLink::IMAGE);
        if (isset($URLs[2])) $elImage->linkImage = new TexyLink($this->texy, $URLs[2], TexyLink::LINKED_IMAGE);

        $elImage->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

        $keyImage = $this->texy->mark($elImage->__toString(), Texy::CONTENT_NONE); // !!!

        if ($mLink) {
            /*
            if ($mLink === ':') {
                $elImage->requireLinkImage();
                if ($elImage->linkImage) $link = $elImage->linkImage;
            } else {
                $link = new TexyLink($this->texy, $mLink, TexyLink::DIRECT);
            }

            $keyOpen  = $this->texy->mark($elLink->opening(), Texy::CONTENT_NONE);
            $keyClose = $this->texy->mark($elLink->closing(), Texy::CONTENT_NONE);
            return $keyOpen . $keyImage . $keyClose;
            */
        }

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

    public $modifier;


    public function __construct($texy)
    {
        $this->texy = $texy;
        $this->modifier = new TexyModifier($texy);
    }


    public function setImages($URL = NULL, $URL_over = NULL, $URL_link = NULL)
    {
        if ($URL != NULL) $this->image = new TexyLink($this->texy, $URL, TexyLink::IMAGE);
        if ($URL_over != NULL) $this->overImage = new TexyLink($this->texy, $URL_over, TexyLink::IMAGE);
        if ($URL_link != NULL) $this->linkImage = new TexyLink($this->texy, $URL_link, TexyLink::LINKED_IMAGE);
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
            $this->modifier = clone $elRef->modifier;
        }

        $URLs = explode('|', $URLs . '||');

        // dimensions
        if (preg_match('#^(.*) (?:(\d+)|\?) *x *(?:(\d+)|\?) *()$#U', $URLs[0], $matches)) {
            $URLs[0] = $matches[1];
            $this->setSize($matches[2], $matches[3]);
        }

        $this->setImages($URLs[0], $URLs[1], $URLs[2]);
    }



    public function __toString()
    {
        if (!$this->image) return;  // image URL is required

        $alt = $this->modifier->title !== NULL ? $this->modifier->title : $this->texy->imageModule->defaultAlt;
        $this->modifier->title = NULL;

        $hAlign = $this->modifier->hAlign;
        $this->modifier->hAlign = NULL;

        $el = $this->modifier->generate('img');
        $this->tags[0] = $el;

        if ($hAlign === TexyModifier::HALIGN_LEFT) {
            if ($this->texy->imageModule->leftClass != '')
                $el->class[] = $this->texy->imageModule->leftClass;
            else
                $el->style['float'] = 'left';

        } elseif ($hAlign === TexyModifier::HALIGN_RIGHT)  {

            if ($this->texy->imageModule->rightClass != '')
                $el->class[] = $this->texy->imageModule->rightClass;
            else
                $el->style['float'] = 'right';
        }

        // width x height generate
        $this->requireSize();
        if ($this->width) $el->width = $this->width;
        if ($this->height) $el->height = $this->height;

        // attribute generate
        $this->texy->summary['images'][] = $el->src = $this->image->asURL();

        // onmouseover actions generate
        if ($this->overImage) {
            $el->onmouseover = 'this.src=\''.$this->overImage->asURL().'\'';
            $el->onmouseout = 'this.src=\''.$this->image->asURL().'\'';
            $this->texy->summary['preload'][] = $this->overImage->asURL();
        }

        $el->alt = (string) $alt;

        return parent::__toString();
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
        if (!$this->linkImage && $this->image) {
            $this->linkImage = new TexyLink($this->texy, $this->image->value, TexyLink::LINKED_IMAGE);
        }
    }



} // TexyImages