<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * Copyright (c) 2004-2007 David Grudl aka -dgx- <dave@dgx.cz>
 *
 * @version  $Revision$ $Date$
 * @package  Texy
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();



/**
 * Images module
 */
class TexyImageModule extends TexyModule implements ITexyPreProcess
{
    protected $default = array(
        'image' => TRUE,
        'image/definition' => TRUE,
    );

    /** @var string  root of relative images (http) */
    public $root = 'images/';

    /** @var string  root of linked images (http) */
    public $linkedRoot = 'images/';

    /** @var string  physical location of images on server */
    public $fileRoot = 'images/';

    /** @var string  left-floated images CSS class */
    public $leftClass;

    /** @var string  right-floated images CSS class */
    public $rightClass;

    /** @var string  default alternative text */
    public $defaultAlt = '';

    /** @var string  images onload handler */
    public $onLoad = "var i=new Image();i.src='%i';if(typeof preload=='undefined')preload=new Array();preload[preload.length]=i;this.onload=''";

    private $references = array();




    public function __construct($texy)
    {
        parent::__construct($texy);

        if (isset($_SERVER['SCRIPT_FILENAME'])) {
            // physical location on server
            $this->fileRoot = dirname($_SERVER['SCRIPT_FILENAME']) . '/' . $this->root;
        }
    }



    public function begin()
    {
        // [*image*]:LINK
        $this->texy->registerLinePattern(
            array($this, 'patternImage'),
            '#'.TEXY_IMAGE.TEXY_LINK_N.'??()#Uu',
            'image'
        );
    }



    public function preProcess($text)
    {
        // [*image*]: urls .(title)[class]{style}
        if ($this->texy->allowed['image/definition'])
           $text = preg_replace_callback(
               '#^\[\*([^\n]+)\*\]:\ +(.+)\ *'.TEXY_MODIFIER.'?\s*()$#mUu',
               array($this, 'patternReferenceDef'),
               $text
           );

        return $text;
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

        $image = $this->factoryImage($mURLs, $mMod, FALSE);
        $this->addReference($mRef, $image);
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
                $link = new TexyLink($image->linkedURL === NULL ? $image->URL : $image->linkedURL);
                $link->raw = ':';
                $link->type = TexyLink::IMAGE;
            } else {
                $link = $tx->linkModule->factoryLink($mLink, NULL, NULL);
            }
        } else $link = NULL;

        // event wrapper
        if (is_callable(array($tx->handler, 'image'))) {
            $res = $tx->handler->image($parser, $image, $link);
            if ($res !== Texy::PROCEED) return $res;
        }

        return $this->solve($image, $link);
    }



    /**
     * Adds new named reference to image
     *
     * @param string  reference name
     * @param TexyImage
     * @return void
     */
    public function addReference($name, TexyImage $image)
    {
        $image->name = TexyUtf::strtolower($name);
        $this->references[$image->name] = $image;
    }



    /**
     * Returns named reference
     *
     * @param string  reference name
     * @return TexyImage  reference descriptor (or FALSE)
     */
    public function getReference($name)
    {
        $name = TexyUtf::strtolower($name);
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
            $tx = $this->texy;
            $content = explode('|', $content);
            $image = new TexyImage;

            // dimensions
            $matches = NULL;
            if (preg_match('#^(.*) (?:(\d+)|\?) *x *(?:(\d+)|\?) *()$#U', $content[0], $matches)) {
                $image->URL = trim($matches[1]);
                $image->width = (int) $matches[2];
                $image->height = (int) $matches[3];
            } else {
                $image->URL = trim($content[0]);
            }

            if (!$tx->checkURL($image->URL, 'i')) $image->URL = NULL;

            // onmouseover image
            if (isset($content[1])) {
                $tmp = trim($content[1]);
                if ($tmp !== '' && $tx->checkURL($tmp, 'i')) $image->overURL = $tmp;
            }

            // linked image
            if (isset($content[2])) {
                $tmp = trim($content[2]);
                if ($tmp !== '' && $tx->checkURL($tmp, 'a')) $image->linkedURL = $tmp;
            }
        }

        $image->modifier->setProperties($mod);
        return $image;
    }



    /**
     * Finish invocation
     *
     * @param TexyImage
     * @param TexyLink
     * @return TexyHtml|FALSE
     */
    public function solve(TexyImage $image, $link)
    {
        if ($image->URL == NULL) return FALSE;

        $tx = $this->texy;

        $mod = $image->modifier;
        $alt = $mod->title !== NULL ? $tx->typographyModule->postLine($mod->title) : $this->defaultAlt;
        $mod->title = NULL;
        $hAlign = $mod->hAlign;
        $mod->hAlign = NULL;

        $el = TexyHtml::el('img');
        $el['src'] = NULL; // trick - move to front
        $mod->decorate($tx, $el);
        $el['src'] = Texy::prependRoot($image->URL, $this->root);
        $el['alt'] = (string) $alt;  // needed

        if ($hAlign === TexyModifier::HALIGN_LEFT) {
            if ($this->leftClass != '')
                $el['class'][] = $this->leftClass;
            else
                $el['style']['float'] = 'left';

        } elseif ($hAlign === TexyModifier::HALIGN_RIGHT)  {

            if ($this->rightClass != '')
                $el['class'][] = $this->rightClass;
            else
                $el['style']['float'] = 'right';
        }

        if ($image->width || $image->height) {
            $el['width'] = $image->width;
            $el['height'] = $image->height;

        } else {
            // absolute URL & security check for double dot
            if (Texy::isRelative($image->URL) && strpos($image->URL, '..') === FALSE) {
                $file = rtrim($this->fileRoot, '/\\') . '/' . $image->URL;
                if (is_file($file)) {
                    $size = getImageSize($file);
                    if (is_array($size)) {
                        $image->width = $el['width'] = $size[0];
                        $image->height = $el['height'] = $size[1];
                    }
                }
            }
        }

        // onmouseover actions generate
        if ($image->overURL !== NULL) {
            $overSrc = Texy::prependRoot($image->overURL, $this->root);
            $el['onmouseover'] = 'this.src=\'' . addSlashes($overSrc) . '\'';
            $el['onmouseout'] = 'this.src=\'' . addSlashes($el['src']) . '\'';
            $el['onload'] = str_replace('%i', addSlashes($overSrc), $this->onLoad);
            $tx->summary['preload'][] = $overSrc;
        }

        $tx->summary['images'][] = $el['src'];

        if ($link) return $tx->linkModule->solve($link, $el);

        return $el;
    }


} // TexyImageModule







class TexyImage
{
    /** @var string  base image URL */
    public $URL;

    /** @var string  on-mouse-over image URL */
    public $overURL;

    /** @var string  anchored image URL */
    public $linkedURL;

    /** @var int  optional image width */
    public $width;

    /** @var int  optional image height */
    public $height;

    /** @var TexyModifier */
    public $modifier;

    /** @var string  reference name (if is stored as reference) */
    public $name;



    public function __construct()
    {
        $this->modifier = new TexyModifier;
    }


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
