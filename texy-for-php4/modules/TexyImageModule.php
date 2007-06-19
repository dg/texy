<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * Copyright (c) 2004-2007 David Grudl aka -dgx- (http://www.dgx.cz)
 *
 * @version  $Revision$ $Date$
 * @package  Texy
 */

// security - include texy.php, not this file
if (!class_exists('Texy')) die();



/**
 * Images module
 */
class TexyImageModule extends TexyModule /* implements ITexyPreBlock */
{
    var $syntax = array(
        'image' => TRUE,
        'image/definition' => TRUE,
    ); /* protected */

    var $interface = array('ITexyPreBlock'=>1);

    /** @var string  root of relative images (http) */
    var $root = 'images/';

    /** @var string  root of linked images (http) */
    var $linkedRoot = 'images/';

    /** @var string  physical location of images on server */
    var $fileRoot = 'images/';

    /** @var string  left-floated images CSS class */
    var $leftClass;

    /** @var string  right-floated images CSS class */
    var $rightClass;

    /** @var string  default alternative text */
    var $defaultAlt = '';

    /** @var string  images onload handler */
    var $onLoad = "var i=new Image();i.src='%i';if(typeof preload=='undefined')preload=new Array();preload[preload.length]=i;this.onload=''";

    var $references = array(); /* private */




    function __construct($texy)
    {
        parent::__construct($texy);

        if (isset($_SERVER['SCRIPT_FILENAME'])) {
            // physical location on server
            $this->fileRoot = dirname($_SERVER['SCRIPT_FILENAME']) . '/' . $this->root;
        }
    }



    function begin()
    {
        // [*image*]:LINK
        $this->texy->registerLinePattern(
            array($this, 'patternImage'),
            '#'.TEXY_IMAGE.TEXY_LINK_N.'??()#Uu',
            'image'
        );
    }



    /**
     * Single block pre-processing
     * @param string
     * @param bool
     * @return string
     */
    function preBlock($text, $topLevel)
    {
        // [*image*]: urls .(title)[class]{style}
        if ($topLevel && $this->texy->allowed['image/definition'])
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
    function patternReferenceDef($matches)
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
    function patternImage($parser, $matches)
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
                $link->type = TexyLink_IMAGE;
            } else {
                $link = $tx->linkModule->factoryLink($mLink, NULL, NULL);
            }
        } else $link = NULL;

        // event wrapper
        if (is_callable(array($tx->handler, 'image'))) {
            $res = $tx->handler->image($parser, $image, $link);
            if ($res !== TEXY_PROCEED) return $res;
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
    function addReference($name, /*TexyImage*/ $image)
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
    function getReference($name)
    {
        $name = TexyUtf::strtolower($name);
        if (isset($this->references[$name]))
            return clone ($this->references[$name]);

        return FALSE;
    }


    /**
     * Parses image's syntax
     * @param string  input: small.jpg 80x13 | small-over.jpg | linked.jpg
     * @param string
     * @param bool
     * @return TexyImage
     */
    function factoryImage($content, $mod, $tryRef=TRUE)
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
    function solve(/*TexyImage*/ $image, $link)
    {
        if ($image->URL == NULL) return FALSE;

        $tx = $this->texy;

        $mod = $image->modifier;
        $alt = $mod->title;
        $mod->title = NULL;
        $hAlign = $mod->hAlign;
        $mod->hAlign = NULL;

        $el = TexyHtml::el('img');
        $el->attrs['src'] = NULL; // trick - move to front
        $mod->decorate($tx, $el);
        $el->attrs['src'] = Texy::prependRoot($image->URL, $this->root);
        if (!isset($el->attrs['alt'])) {
            if ($alt !== NULL) $el->attrs['alt'] = $tx->typographyModule->postLine($alt);
            else $el->attrs['alt'] = $this->defaultAlt;
        }

        if ($hAlign === TexyModifier_HALIGN_LEFT) {
            if ($this->leftClass != '')
                $el->attrs['class'][] = $this->leftClass;
            else
                $el->attrs['style']['float'] = 'left';

        } elseif ($hAlign === TexyModifier_HALIGN_RIGHT)  {

            if ($this->rightClass != '')
                $el->attrs['class'][] = $this->rightClass;
            else
                $el->attrs['style']['float'] = 'right';
        }

        if ($image->width || $image->height) {
            $el->attrs['width'] = $image->width;
            $el->attrs['height'] = $image->height;

        } else {
            // absolute URL & security check for double dot
            if (Texy::isRelative($image->URL) && strpos($image->URL, '..') === FALSE) {
                $file = rtrim($this->fileRoot, '/\\') . '/' . $image->URL;
                if (is_file($file)) {
                    $size = getImageSize($file);
                    if (is_array($size)) {
                        $image->width = $el->attrs['width'] = $size[0];
                        $image->height = $el->attrs['height'] = $size[1];
                    }
                }
            }
        }

        // onmouseover actions generate
        if ($image->overURL !== NULL) {
            $overSrc = Texy::prependRoot($image->overURL, $this->root);
            $el->attrs['onmouseover'] = 'this.src=\'' . addSlashes($overSrc) . '\'';
            $el->attrs['onmouseout'] = 'this.src=\'' . addSlashes($el->attrs['src']) . '\'';
            $el->attrs['onload'] = str_replace('%i', addSlashes($overSrc), $this->onLoad);
            $tx->summary['preload'][] = $overSrc;
        }

        $tx->summary['images'][] = $el->attrs['src'];

        if ($link) return $tx->linkModule->solve($link, $el);

        return $el;
    }


} // TexyImageModule







class TexyImage
{
    /** @var string  base image URL */
    var $URL;

    /** @var string  on-mouse-over image URL */
    var $overURL;

    /** @var string  anchored image URL */
    var $linkedURL;

    /** @var int  optional image width */
    var $width;

    /** @var int  optional image height */
    var $height;

    /** @var TexyModifier */
    var $modifier;

    /** @var string  reference name (if is stored as reference) */
    var $name;



    function __construct()
    {
        $this->modifier = new TexyModifier;
    }


    function __clone()
    {
        if ($this->modifier)
            $this->modifier = clone ($this->modifier);
    }


    function TexyImage()  /* PHP 4 constructor */
    {
        // generate references (see http://www.dgx.cz/trine/item/how-to-emulate-php5-object-model-in-php4)
        foreach ($this as $key => $foo) $GLOBALS['$$HIDDEN$$'][] = & $this->$key;

        // call php5 constructor
        $args = func_get_args();
        call_user_func_array(array(&$this, '__construct'), $args);
    }
}
