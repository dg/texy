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
    protected $allow = array('Image');

    /** @var string  root of relative images (http) */
    public $webRoot = 'images/';

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

    protected $references = array();

    // back compatiblity
    public $root;
    public $rootPrefix = '';




    public function __construct($texy)
    {
        parent::__construct($texy);

        // back compatiblity
        $this->root = & $this->webRoot;
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
            'Image'
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

        if (!$modifier) $modifier = new TexyModifier($this->texy);
        list($URL, $overURL, $width, $height) = self::parseContent($URLs);
        $this->references[$name] = array(
            'URL' => $URL,
            'overURL' => $overURL,
            'modifier' => $modifier,
            'width' => $width,
            'height' => $height,
            'name' => $name,
        );
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

        if (isset($this->references[$name]))
            return $this->references[$name];

        return FALSE;
    }


    /**
     * Parses image's syntax
     * @param string  input: small.jpg 80x13 | small-over.jpg
     * @return array  output: ('small.jpg', 'small-over.jpg', 80, 13)
     */
    static private function parseContent($content)
    {
        $content = explode('|', $content);

        // dimensions
        if (preg_match('#^(.*) (?:(\d+)|\?) *x *(?:(\d+)|\?) *()$#U', $content[0], $matches)) {
            $URL = trim($matches[1]);
            $width = (int) $matches[2];
            $height = (int) $matches[3];

        } else {
            $URL = trim($content[0]);
            $width = $height = NULL;
        }

        // onmouseover actions generate
        $overURL = NULL;
        if (isset($content[1])) {
            $content[1] = trim($content[1]);
            if ($content[1] !== '') $overURL = $content[1];
        }
        return array($URL, $overURL, $width, $height);
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

        list($URL, $overURL, $width, $height, $mod) = $this->factory1($mURLs, $mMod1, $mMod2, $mMod3, $mMod4);
        $el = $this->factoryEl($URL, $overURL, $width, $height, $mod, $mLink);
        $mark = $el->startMark($this->texy);

        if ($mLink) {
            if ($mLink === ':') {
                $elLink = $tx->linkModule->factoryEl(
                    new TexyLink($URL, $this->linkedRoot, TexyLink::IMAGE),
                    new TexyModifier($tx)
                );
            } else {
                $elLink = $tx->linkModule->factory($mLink, NULL, NULL, NULL, NULL);
            }
            $mark = $elLink->startMark($tx) . $mark . $elLink->endMark($tx);
        }

        return $mark;
    }



    public function factory1($mContent, $mMod1, $mMod2, $mMod3, $mMod4)
    {
        $mContent = trim($mContent);

        $ref = $this->getReference($mContent);
        if ($ref) {
            $URL = $ref['URL'];
            $overURL = $ref['overURL'];
            $width = $ref['width'];
            $height = $ref['height'];
            $modifier = clone $ref['modifier'];
        } else {
            list($URL, $overURL, $width, $height) = self::parseContent($mContent);
            $modifier = new TexyModifier($this->texy);
        }

        $modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        return array($URL, $overURL, $width, $height, $modifier);
    }



    public function factoryEl($URL, $overURL, $width, $height, $modifier, $link)
    {
        $tx = $this->texy;
        $src = TexyLink::adjustURL($URL, $this->webRoot, TRUE);
        $tx->summary['images'][] = $src;

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

        } elseif (is_file($this->fileRoot . '/' . $URL)) {
            $size = getImageSize($this->fileRoot . '/' . $URL);
            if (is_array($size)) {
                $el->width = $size[0];
                $el->height = $size[1];
            }
        }

        $el->src = $src;
        $el->alt = (string) $alt;  // needed

        // onmouseover actions generate
        if ($overURL !== NULL) {
            $overSrc = TexyLink::adjustURL($overURL, $this->webRoot, TRUE);
            $el->onmouseover = 'this.src=\'' . addSlashes($overSrc) . '\'';
            $el->onmouseout = 'this.src=\'' . addSlashes($src) . '\'';
            $tx->summary['preload'][] = $overSrc;
        }

        $tmp = $el->alt; unset($el->alt); $el->alt = $tmp;
        return $el;
    }

} // TexyImageModule
