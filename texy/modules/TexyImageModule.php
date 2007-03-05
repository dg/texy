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
    protected $default = array(
        'image' => TRUE,
    );

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
        // [*image*]:LINK
        $this->texy->registerLinePattern(
            array($this, 'patternImage'),
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

        $image = $this->factoryImage($URLs, NULL, FALSE);
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
     * @param string
     * @param bool
     * @return TexyImage
     */
    public function factoryImage($content, $mod, $tryRef=TRUE)
    {
        $image = $tryRef ? $this->getReference(trim($content)) : FALSE;

        if (!$image) {
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

            $image->modifier = new TexyModifier;
        }

        $image->modifier->setProperties($mod);
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
            array($this, 'patternReferenceDef'),
            $text
        );
    }



    /**
     * Callback for: [*image*]: urls .(title)[class]{style}
     *
     * @param array      regexp matches
     * @return string
     */
    public function patternReferenceDef($matches)
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
     * Callback for [* small.jpg 80x13 | small-over.jpg | big.jpg .(alternative text)[class]{style}>]:LINK
     *
     * @param TexyLineParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|string|FALSE
     */
    public function patternImage($parser, $matches)
    {
        list(, $mURLs, $mMod, $mAlign, $mLink) = $matches;
        //    [1] => URLs
        //    [2] => .(title)[class]{style}<>
        //    [3] => * < >
        //    [4] => url | [ref] | [*image*]

        $tx = $this->texy;

        $image = $this->factoryImage($mURLs, $mMod.$mAlign);

        if ($mLink) {
            if ($mLink === ':') {
                $link = new TexyLink;
                $link->URL = $image->linkedURL === NULL ? $image->imageURL : $image->linkedURL;
                $link->type = TexyLink::AUTOIMAGE;
                $link->modifier = new TexyModifier;
            } else {
                $link = $tx->linkModule->factoryLink($mLink, NULL, NULL);
            }
        } else $link = NULL;

        // event wrapper
        if (is_callable(array($tx->handler, 'image'))) {
            $res = $tx->handler->image($parser, $image, $link);
            if ($res !== NULL) return $res;
        }

        return $this->solve($image, $link);
    }



    /**
     * Finish invocation
     *
     * @param TexyImage
     * @param TexyLink
     * @return TexyHtml
     */
    public function solve(TexyImage $image, $link)
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
                $image->width = $el->width = $size[0];
                $image->height = $el->height = $size[1];
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

        $tx->summary['images'][] = $el->src;

        if ($link) return $tx->linkModule->solve($link, $el);

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
