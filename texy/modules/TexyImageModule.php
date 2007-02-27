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
    protected $allow = array('image');

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
            $this,
            'processLine',
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

        $ref = $this->parseContent($URLs);
        $ref['modifier'] = $modifier;
        $ref['name'] = $name;
        $this->references[$name] = $ref;
    }



    /**
     * Returns named reference
     *
     * @param string  reference name
     * @return array  reference descriptor (or FALSE)
     */
    public function getReference($name)
    {
        if (function_exists('mb_strtolower')) {
            $name = mb_strtolower($name, 'UTF-8');
        } else {
            $name = strtolower($name);
        }

        if (isset($this->references[$name])) {
            $ref = $this->references[$name];
            $ref['modifier'] = empty($ref['modifier'])
                ? new TexyModifier($this->texy)
                : clone $ref['modifier'];
            return $ref;
        }

        return FALSE;
    }



    /**
     * Parses image's syntax
     * @param string  input: small.jpg 80x13 | small-over.jpg | linked.jpg
     * @return array  output
     */
    private function parseContent($content)
    {
        $content = explode('|', $content);
        $req = array();

        // dimensions
        if (preg_match('#^(.*) (?:(\d+)|\?) *x *(?:(\d+)|\?) *()$#U', $content[0], $matches)) {
            $req['imageURL'] = trim($matches[1]);
            $req['width'] = (int) $matches[2];
            $req['height'] = (int) $matches[3];

        } else {
            $req['imageURL'] = trim($content[0]);
            $req['width'] = $req['height'] = NULL;
        }

        // onmouseover image
        $req['overURL'] = NULL;
        if (isset($content[1])) {
            $tmp = trim($content[1]);
            if ($tmp !== '') $req['overURL'] = $tmp;
        }

        // linked image
        $req['linkedURL'] = NULL;
        if (isset($content[2])) {
            $tmp = trim($content[1]);
            if ($tmp !== '') $req['linkedURL'] = $tmp;
        }

        return $req;
    }


    public function parse($mURLs, $mMod1, $mMod2, $mMod3, $mMod4)
    {
        $req = $this->getReference(trim($mURLs));
        if (!$req) {
            $req = $this->parseContent($mURLs);
            $req['modifier'] = new TexyModifier($this->texy);
        }
        $req['modifier']->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        return $req;
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
        list(, $mRef, $mURLs, $mMod1, $mMod2, $mMod3) = $matches;
        //    [1] => [* (reference) *]
        //    [2] => urls
        //    [3] => (title)
        //    [4] => [class]
        //    [5] => {style}

        $mod = new TexyModifier($this->texy);
        $mod->setProperties($mMod1, $mMod2, $mMod3);
        $this->addReference($mRef, $mURLs, $mod);
        return '';
    }



    /**
     * Callback function: [* small.jpg 80x13 | small-over.jpg .(alternative text)[class]{style}>]:LINK
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

        $tx = $this->texy;

        $req = $this->parse($mURLs, $mMod1, $mMod2, $mMod3, $mMod4);
        $el = $this->factory($req);

        if (is_callable(array($tx->handler, 'image')))
            $tx->handler->image($tx, $req, $el);

        $tx->summary['images'][] = $el->src;

        if ($mLink) {
            if ($mLink === ':') {
                $reqL = array(
                    'URL' => empty($req['linkedURL']) ? $req['imageURL'] : $req['linkedURL'],
                    'image' => TRUE,
                    'modifier' => new TexyModifier($tx),
                );
            } else {
                $reqL = $tx->linkModule->parse($mLink, NULL, NULL, NULL, NULL);
            }

            $elLink = $tx->linkModule->factory($reqL);
            $tx->summary['links'][] = $elLink->href;

            $elLink->addChild($el);
            return $elLink->toText($tx);
        }

        return $el->toText($tx);
    }



    public function factory($req)
    {
        extract($req);

        $tx = $this->texy;
        $src = Texy::completeURL($imageURL, $this->root);
        $file = Texy::completePath($imageURL, $this->fileRoot);

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

        $alt = $modifier->title !== NULL ? $modifier->title : $this->defaultAlt;
        $modifier->title = NULL;

        $hAlign = $modifier->hAlign;
        $modifier->hAlign = NULL;

        $el = $modifier->generate('img');

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

        if ($width) {
            $el->width = $width;
            $el->height = $height;

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
        if ($overURL !== NULL) {
            $overSrc = Texy::completeURL($overURL, $this->root);
            $el->onmouseover = 'this.src=\'' . addSlashes($overSrc) . '\'';
            $el->onmouseout = 'this.src=\'' . addSlashes($src) . '\'';
            static $counter; $counter++;
            $attrs['onload'] = "preload_$counter=new Image();preload_$counter.src='" . addSlashes($overSrc) . "';this.onload=''";
            $tx->summary['preload'][] = $overSrc;
        }

        return $el;
    }

} // TexyImageModule
