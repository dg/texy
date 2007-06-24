<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004-2007 David Grudl aka -dgx- (http://www.dgx.cz)
 * @license    GNU GENERAL PUBLIC LICENSE version 2
 * @version    $Revision$ $Date$
 * @category   Text
 * @package    Texy
 */

// security - include texy.php, not this file
if (!class_exists('Texy')) die();




/** @var array */
$GLOBALS['TexyLinkModule::$deadlock'] = NULL; /* class static private property */


/**
 * Links module
 */
class TexyLinkModule extends TexyModule /* implements ITexyPreBlock */
{
    var $syntax = array(
        'link/reference' => TRUE,
        'link/email' => TRUE,
        'link/url' => TRUE,
        'link/definition' => TRUE,
    ); /* protected */

    var $interface = array('ITexyPreBlock'=>1);

    /** @var string  root of relative links */
    var $root = '';

    /** @var string image popup event */
    var $imageOnClick = 'return !popupImage(this.href)';  //

    /** @var string class 'popup' event */
    var $popupOnClick = 'return !popup(this.href)';

    /** @var bool  always use rel="nofollow" for absolute links? */
    var $forceNoFollow = FALSE;

    /** @var array link references */
    var $references = array(); /* protected */

    function begin()
    {
        $GLOBALS['TexyLinkModule::$deadlock']= array();

        $tx = $this->texy;
        // [reference]
        $tx->registerLinePattern(
            array($this, 'patternReference'),
            '#(\[[^\[\]\*\n'.TEXY_MARK.']+\])#U',
            'link/reference'
        );

        // direct url and email
        $tx->registerLinePattern(
            array($this, 'patternUrlEmail'),
            '#(?<=^|[\s(\[<:])(?:https?://|www\.|ftp://)[a-z0-9.-][/a-z\d+\.~%&?@=_:;\#,-]+[/\w\d+~%?@=_\#]#iu',
            'link/url'
        );

        $tx->registerLinePattern(
            array($this, 'patternUrlEmail'),
            '#(?<=^|[\s(\[\<:])'.TEXY_EMAIL.'#iu',
            'link/email'
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
        // [la trine]: http://www.dgx.cz/trine/ text odkazu .(title)[class]{style}
        if ($topLevel && $this->texy->allowed['link/definition'])
            $text = preg_replace_callback(
                '#^\[([^\[\]\#\?\*\n]+)\]: +(\S+)(\ .+)?'.TEXY_MODIFIER.'?\s*()$#mUu',
                array($this, 'patternReferenceDef'),
                $text
            );

        return $text;
    }



    /**
     * Callback for: [la trine]: http://www.dgx.cz/trine/ text odkazu .(title)[class]{style}
     *
     * @param array      regexp matches
     * @return string
     */
    function patternReferenceDef($matches) /* private */
    {
        list(, $mRef, $mLink, $mLabel, $mMod) = $matches;
        //    [1] => [ (reference) ]
        //    [2] => link
        //    [3] => ...
        //    [4] => .(title)[class]{style}

        $link = new TexyLink($mLink);
        $link->label = trim($mLabel);
        $link->modifier->setProperties($mMod);
        $this->checkLink($link);
        $this->addReference($mRef, $link);
        return '';
    }




    /**
     * Callback for: [ref]
     *
     * @param TexyLineParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|string|FALSE
     */
    function patternReference($parser, $matches)
    {
        list(, $mRef) = $matches;
        //    [1] => [ref]

        $tx = $this->texy;
        $name = substr($mRef, 1, -1);
        $link = $this->getReference($name);

        if (!$link) {
            // try handler
            if (is_callable(array($tx->handler, 'newReference'))) {
                $res = $tx->handler->newReference($parser, $name);
                if ($res !== TEXY_PROCEED) return $res;
            }

            // no change
            return FALSE;
        }

        $link->type = TexyLink_BRACKET;

        if ($link->label != '') {  // NULL or ''
            // prevent deadlock
            if (isset($GLOBALS['TexyLinkModule::$deadlock'][$link->name])) {
                $content = $link->label;
            } else {
                $GLOBALS['TexyLinkModule::$deadlock'][$link->name] = TRUE;
                $lineParser = new TexyLineParser($tx);
                $content = $lineParser->parse($link->label);
                unset($GLOBALS['TexyLinkModule::$deadlock'][$link->name]);
            }
        } else {
            $content = $this->textualURL($link);
        }

        // event wrapper
        if (is_callable(array($tx->handler, 'linkReference'))) {
            $res = $tx->handler->linkReference($parser, $link, $content);
            if ($res !== TEXY_PROCEED) return $res;
        }

        return $this->solve($link, $content);
    }



    /**
     * Callback for: http://www.dgx.cz   dave@dgx.cz
     *
     * @param TexyLineParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|string|FALSE
     */
    function patternUrlEmail($parser, $matches, $name)
    {
        list($mURL) = $matches;
        //    [0] => URL

        $link = new TexyLink($mURL);
        $this->checkLink($link);
        $content = $this->textualURL($link);

        // event wrapper
        $method = $name === 'link/email' ? 'linkEmail' : 'linkURL';
        if (is_callable(array($this->texy->handler, $method))) {
            $res = $this->texy->handler->$method($parser, $link, $content);
            if ($res !== TEXY_PROCEED) return $res;
        }

        return $this->solve($link, $content);
    }





    /**
     * Adds new named reference
     *
     * @param string  reference name
     * @param TexyLink
     * @return void
     */
    function addReference($name, /*TexyLink*/ $link)
    {
        $link->name = TexyUtf::strtolower($name);
        $this->references[$link->name] = $link;
    }



    /**
     * Returns named reference
     *
     * @param string  reference name
     * @return TexyLink reference descriptor (or FALSE)
     */
    function getReference($name)
    {
        $name = TexyUtf::strtolower($name);
        if (isset($this->references[$name])) {
            return clone ($this->references[$name]);

        } else {
            $pos = strpos($name, '?');
            if ($pos === FALSE) $pos = strpos($name, '#');
            if ($pos !== FALSE) { // try to extract ?... #... part
                $name2 = substr($name, 0, $pos);
                if (isset($this->references[$name2])) {
                    $link = clone ($this->references[$name2]);
                    $link->URL .= substr($name, $pos);
                    return $link;
                }
            }
        }

        return FALSE;
    }



    /**
     * @param string
     * @param string
     * @param string
     * @return TexyLink
     */
    function factoryLink($dest, $mMod, $label)
    {
        $tx = $this->texy;
        $type = TexyLink_COMMON;

        // [ref]
        if (strlen($dest)>1 && $dest{0} === '[' && $dest{1} !== '*') {
            $type = TexyLink_BRACKET;
            $dest = substr($dest, 1, -1);
            $link = $this->getReference($dest);

        // [* image *]
        } elseif (strlen($dest)>1 && $dest{0} === '[' && $dest{1} === '*') {
            $type = TexyLink_IMAGE;
            $dest = trim(substr($dest, 2, -2));
            $image = $tx->imageModule->getReference($dest);
            if ($image) {
                $link = new TexyLink($image->linkedURL === NULL ? $image->URL : $image->linkedURL);
                $link->modifier = $image->modifier;
            }
        }

        if (empty($link)) {
            $link = new TexyLink(trim($dest));
            $this->checkLink($link);
        }

        if (strpos($link->URL, '%s') !== FALSE) {
            $link->URL = str_replace('%s', urlencode($tx->_toText($label)), $link->URL);
        }
        $link->modifier->setProperties($mMod);
        $link->type = $type;
        return $link;
    }



    /**
     * Finish invocation
     *
     * @param TexyLink
     * @param TexyHtml|string
     * @return TexyHtml|string
     */
    function solve($link, $content=NULL)
    {
        if ($link->URL == NULL) return $content;

        $tx = $this->texy;

        $el = TexyHtml::el('a');

        if (empty($link->modifier)) {
            $nofollow = $popup = FALSE;
        } else {
            $classes = array_flip($link->modifier->classes);
            $nofollow = isset($classes['nofollow']);
            $popup = isset($classes['popup']);
            unset($classes['nofollow'], $classes['popup']);
            $link->modifier->classes = array_flip($classes);
            $el->attrs['href'] = NULL; // trick - move to front
            $link->modifier->decorate($tx, $el);
        }

        if ($link->type === TexyLink_IMAGE) {
            // image
            $el->attrs['href'] = Texy::prependRoot($link->URL, $tx->imageModule->linkedRoot);
            $el->attrs['onclick'] = $this->imageOnClick;

        } else {
            $el->attrs['href'] = Texy::prependRoot($link->URL, $this->root);

            // rel="nofollow"
            if ($nofollow || ($this->forceNoFollow && strpos($el->attrs['href'], '//') !== FALSE))
                $el->attrs['rel'] = 'nofollow';
        }

        // popup on click
        if ($popup) $el->attrs['onclick'] = $this->popupOnClick;

        if ($content !== NULL) {
            if (is_a($content, 'TexyHtml'))
                $el->addChild($content);
            else
                $el->setText($content);
        }

        $tx->summary['links'][] = $el->attrs['href'];

        return $el;
    }



    /**
     * Checks and corrects $URL
     * @param TexyLink
     * @return void
     */
    function checkLink($link) /* private */
    {
        $tmp = $link->URL;

        if (strncasecmp($link->URL, 'www.', 4) === 0) {
            // special supported case
            $link->URL = 'http://' . $link->URL;

        } elseif (preg_match('#'.TEXY_EMAIL.'$#iA', $link->URL)) {
            // email
            $link->URL = 'mailto:' . $link->URL;

        } elseif (!$this->texy->checkURL($link->URL, 'a')) {
            $link->URL = NULL;

        } else {
            $link->URL = str_replace('&amp;', '&', $link->URL); // replace unwanted &amp;
        }

        // save for custom handlers and next generations :-)
        if ($link->URL !== $tmp) $link->raw = $tmp;
    }



    /**
     * Returns textual representation of URL
     * @param TexyLink
     * @return string
     */
    function textualURL($link) /* private */
    {
        $URL = $link->raw === NULL ? $link->URL : $link->raw;

        if (preg_match('#^'.TEXY_EMAIL.'$#i', $URL)) { // email
            return $this->texy->obfuscateEmail
                   ? str_replace('@', $this->texy->protect("&#64;<!---->", TEXY_CONTENT_MARKUP), $URL)
                   : $URL;
        }

        if (preg_match('#^(https?://|ftp://|www\.|/)#i', $URL)) {

            if (strncasecmp($URL, 'www.', 4) === 0) $parts = @parse_url('none://'.$URL);
            else $parts = @parse_url($URL);

            if ($parts === FALSE) return $URL;

            $res = '';
            if (isset($parts['scheme']) && $parts['scheme'] !== 'none')
                $res .= $parts['scheme'] . '://';

            if (isset($parts['host']))
                $res .= $parts['host'];

            if (isset($parts['path']))
                $res .=  (strlen($parts['path']) > 16 ? ('/...' . preg_replace('#^.*(.{0,12})$#U', '$1', $parts['path'])) : $parts['path']);

            if (isset($parts['query'])) {
                $res .= strlen($parts['query']) > 4 ? '?...' : ('?'.$parts['query']);
            } elseif (isset($parts['fragment'])) {
                $res .= strlen($parts['fragment']) > 4 ? '#...' : ('#'.$parts['fragment']);
            }
            return $res;
        }

        return $URL;
    }



}










    /** @see $type */
define('TexyLink_COMMON',  1);
define('TexyLink_BRACKET', 2);
define('TexyLink_IMAGE', 3);

class TexyLink
{
    /** @var string  URL in resolved form */
    var $URL;

    /** @var string  URL as written in text */
    var $raw;

    /** @var TexyModifier */
    var $modifier;

    /** @var int  how was link created? */
    var $type = TexyLink_COMMON;

    /** @var string  optional label, used by references */
    var $label;

    /** @var string  reference name (if is stored as reference) */
    var $name;



    function __construct($URL)
    {
        $this->URL = $URL;
        $this->modifier = new TexyModifier;
    }


    function __clone()
    {
        if ($this->modifier)
            $this->modifier = clone ($this->modifier);
    }


    function TexyLink()  /* PHP 4 constructor */
    {
        // generate references (see http://www.dgx.cz/trine/item/how-to-emulate-php5-object-model-in-php4)
        foreach ($this as $key => $foo) $GLOBALS['$$HIDDEN$$'][] = & $this->$key;

        // call php5 constructor
        $args = func_get_args();
        call_user_func_array(array(&$this, '__construct'), $args);
    }
}
