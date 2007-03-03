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
    protected $default = array('image' => TRUE);

    /** @var string  root of relative images (http) */
    public $root = 'images/';

    /** @var string  root of linked images (http) */
    public $linkedRoot = 'images/';

    /** @var string  physical location of images on server */
    public $fileRoot;

    /** @var string  left-floated images CSS class */
    public $leftClass;

    /** @var string  right-floated images CSS class */
    public $rightClass;

    /** @var string  default alternative text */
    public $defaultAlt = '';

    private $references = array();

    // back compatiblity
    public $rootPrefix = '';




    public function __construct($texy)
    {
        parent::__construct($texy);

        // back compatiblity
        $this->rootPrefix = & $this->fileRoot;

        if (isset($_SERVER['SCRIPT_NAME'])) {
            $this->fileRoot = dirname($_SERVER['SCRIPT_NAME']); // physical location on server
        }
    }



    public function init()
    {
        // [*image*]:LINK    where LINK is:   url | [ref] | [*image*]
        $this->texy->registerLinePattern(
            array($this, 'processLine'),
            '#'.TEXY_IMAGE.TEXY_LINK_N.'??()#U',
            'image'
        );
    }



    /**
     * Adds new named reference to image
     *
     * @param string  reference name
     * @param string  URLs
     * @param TexyModifier  optional modifier
     * @return void
     */
    public function addReference($name, $URLs, $modifier=NULL)
    {
        if (function_exists('mb_strtolower')) {
            $name = mb_strtolower($name, 'UTF-8');
        } else {
            $name = strtolower($name);
        }

        if (!$modifier) $modifier = new TexyModifier;

        $image = $this->parseContent($URLs);
        $image->modifier = $modifier;
        $image->name = $name;
        $this->references[$name] = $image;
    }



    /**
     * Returns named reference
     *
     * @param string  reference name
     * @return TexyImage  reference descriptor (or FALSE)
     */
    public function getReference($name)
    {
        if (function_exists('mb_strtolower')) {
            $name = mb_strtolower($name, 'UTF-8');
        } else {
            $name = strtolower($name);
        }

        if (isset($this->references[$name]))
            return clone $this->references[$name];

        return FALSE;
    }



    /**
     * Parses image's syntax
     * @param string  input: small.jpg 80x13 | small-over.jpg | linked.jpg
     * @return TexyImage  output
     */
    private function parseContent($content)
    {
        $content = explode('|', $content);
        $image = new TexyImage;

        // dimensions
        if (preg_match('#^(.*) (?:(\d+)|\?) *x *(?:(\d+)|\?) *()$#U', $content[0], $matches)) {
            $image->imageURL = trim($matches[1]);
            $image->width = (int) $matches[2];
            $image->height = (int) $matches[3];
        } else {
            $image->imageURL = trim($content[0]);
        }

        // onmouseover image
        if (isset($content[1])) {
            $tmp = trim($content[1]);
            if ($tmp !== '') $image->overURL = $tmp;
        }

        // linked image
        if (isset($content[2])) {
            $tmp = trim($content[2]);
            if ($tmp !== '') $image->linkedURL = $tmp;
        }

        return $image;
    }



    /**
     * @return TexyImage
     */
    public function parse($mURLs, $mMod)
    {
        $image = $this->getReference(trim($mURLs));
        if (!$image) {
            $image = $this->parseContent($mURLs);
            $image->modifier = new TexyModifier;
        }
        $image->modifier->setProperties($mMod);
        return $image;
    }


    /**
     * Text preprocessing
     */
    public function preProcess($text)
    {
        // [*image*]: urls .(title)[class]{style}
        return preg_replace_callback(
            '#^\[\*([^\n]+)\*\]:\ +(.+)\ *'.TEXY_MODIFIER.'?()$#mU',
            array($this, 'processReferenceDefinition'),
            $text
        );
    }



    /**
     * Callback function: [*image*]: urls .(title)[class]{style}
     * @return string
     */
    public function processReferenceDefinition($matches)
    {
        list(, $mRef, $mURLs, $mMod) = $matches;
        //    [1] => [* (reference) *]
        //    [2] => urls
        //    [3] => .(title)[class]{style}<>

        $mod = new TexyModifier($mMod);
        $this->addReference($mRef, $mURLs, $mod);
        return '';
    }



    /**
     * Callback function: [* small.jpg 80x13 | small-over.jpg | big.jpg .(alternative text)[class]{style}>]:LINK
     * @return string
     */
    public function processLine($parser, $matches)
    {
        list(, $mURLs, $mMod, $mAlign, $mLink) = $matches;
        //    [1] => URLs
        //    [2] => .(title)[class]{style}<>
        //    [3] => * < >
        //    [4] => url | [ref] | [*image*]

        $tx = $this->texy;
        $user = $link = NULL;

        $image = $this->parse($mURLs, $mMod.$mAlign);

        if ($mLink) {
            if ($mLink === ':') {
                $link = new TexyLink;
                $link->URL = $image->linkedURL === NULL ? $image->imageURL : $image->linkedURL;
                $link->type = TexyLink::AUTOIMAGE;
                $link->modifier = new TexyModifier;
            } else {
                $link = $tx->linkModule->parse($mLink, NULL, NULL, NULL, NULL);
            }
        }

        if (is_callable(array($tx->handler, 'image'))) {
            $el = $tx->handler->image($tx, $image, $link, $user);
            if ($el) return $el;
        }

        $el = $this->factory($image);

        $tx->summary['images'][] = $el->src;

        if ($link) {
            $el = $tx->linkModule->factory($link)->setContent($el);
            $tx->summary['links'][] = $el->href;
        }

        if (is_callable(array($tx->handler, 'image2')))
            $tx->handler->image2($tx, $el, $user);

        return $el;
    }



    /**
     * @param TexyImage
     * @return TexyHtml
     */
    public function factory($image)
    {
        $tx = $this->texy;
        $src = Texy::completeURL($image->imageURL, $this->root);
        $file = Texy::completePath($image->imageURL, $this->fileRoot);

        if (substr($src, -4) === '.swf') {
/*
    <!--[if !IE]> -->
    <object type="application/x-shockwave-flash" data="movie.swf" width="300" height="135">
    <!-- <![endif]-->

    <!--[if IE]>
    <object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="300" height="135"
        codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,0,0">
        <param name="movie" value="movie.swf" />
    <!--><!--dgx-->
        <param name="loop" value="true" />
        <param name="menu" value="false" />

        <p><?=$modifier->title !== NULL ? $modifier->title : $this->defaultAlt;?></p>
    </object>
    <!-- <![endif]-->
*/
        }

        $mod = $image->modifier;
        $alt = $mod->title !== NULL ? $mod->title : $this->defaultAlt;
        $mod->title = NULL;

        $hAlign = $mod->hAlign;
        $mod->hAlign = NULL;

        $el = TexyHtml::el('img');
        $mod->decorate($tx, $el);

        if ($hAlign === TexyModifier::HALIGN_LEFT) {
            if ($this->leftClass != '')
                $el->class[] = $this->leftClass;
            else
                $el->style['float'] = 'left';

        } elseif ($hAlign === TexyModifier::HALIGN_RIGHT)  {

            if ($this->rightClass != '')
                $el->class[] = $this->rightClass;
            else
                $el->style['float'] = 'right';
        }

        if ($image->width || $image->height) {
            $el->width = $image->width;
            $el->height = $image->height;

        } elseif (is_file($file)) {
            $size = getImageSize($file);
            if (is_array($size)) {
                $el->width = $size[0];
                $el->height = $size[1];
            }
        }

        $el->src = $src;
        $el->alt = (string) $alt;  // needed


        // onmouseover actions generate
        if ($image->overURL !== NULL) {
            $overSrc = Texy::completeURL($image->overURL, $this->root);
            $el->onmouseover = 'this.src=\'' . addSlashes($overSrc) . '\'';
            $el->onmouseout = 'this.src=\'' . addSlashes($src) . '\'';
            static $counter; $counter++;
            $attrs['onload'] = "preload_$counter=new Image();preload_$counter.src='" . addSlashes($overSrc) . "';this.onload=''";
            $tx->summary['preload'][] = $overSrc;
        }

        return $el;
    }

} // TexyImageModule






class TexyImage
{
    public $imageURL;
    public $overURL;
    public $linkedURL;
    public $width;
    public $height;
    public $modifier;
    public $name;


    public function __clone()
    {
        if ($this->modifier)
            $this->modifier = clone $this->modifier;
    }

    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }
}
