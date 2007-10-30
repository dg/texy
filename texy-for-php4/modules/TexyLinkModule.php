<?php

/**
 * Texy! - web text markup-language (for PHP 4)
 * --------------------------------------------
 *
 * Copyright (c) 2004, 2007 David Grudl aka -dgx- (http://www.dgx.cz)
 *
 * This source file is subject to the GNU GPL license that is bundled
 * with this package in the file license.txt.
 *
 * For more information please see http://texy.info/
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004, 2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @category   Text
 * @package    Texy
 * @link       http://texy.info/
 */



/** @var array */
$GLOBALS['TexyLinkModule::$deadlock'] = NULL; /* class private static property */


/**
 * Links module
 * @package Texy
 * @version $Revision$ $Date$
 */
class TexyLinkModule extends TexyModule
{
    /** @var string  root of relative links */
    var $root = '';

    /** @var string image popup event */
    var $imageOnClick = 'return !popupImage(this.href)';  //

    /** @var string class 'popup' event */
    var $popupOnClick = 'return !popup(this.href)';

    /** @var bool  always use rel="nofollow" for absolute links? */
    var $forceNoFollow = FALSE;

    /** @var array link references */
    var $references = array(); /* private */



    function __construct($texy)
    {
        $this->texy = $texy;

        $texy->allowed['link/definition'] = TRUE;
        $texy->addHandler('newReference', array($this, 'solveNewReference'));
        $texy->addHandler('linkReference', array($this, 'solve'));
        $texy->addHandler('linkEmail', array($this, 'solveUrlEmail'));
        $texy->addHandler('linkURL', array($this, 'solveUrlEmail'));
        $texy->addHandler('beforeParse', array($this, 'beforeParse'));

        // [reference]
        $texy->registerLinePattern(
            array($this, 'patternReference'),
            '#(\[[^\[\]\*\n'.TEXY_MARK.']+\])#U',
            'link/reference'
        );

        // direct url and email
        $texy->registerLinePattern(
            array($this, 'patternUrlEmail'),
            '#(?<=^|[\s([<:\x17])(?:https?://|www\.|ftp://)[a-z0-9.-][/a-z\d+\.~%&?@=_:;\#,-]+[/\w\d+~%?@=_\#]#iu',
            'link/url'
        );

        $texy->registerLinePattern(
            array($this, 'patternUrlEmail'),
            '#(?<=^|[\s([<:\x17])'.TEXY_EMAIL.'#iu',
            'link/email'
        );
    }



    /**
     * Text pre-processing
     * @param Texy
     * @param string
     * @return void
     */
    function beforeParse($texy, & $text)
    {
        $GLOBALS['TexyLinkModule::$deadlock']= array();

        // [la trine]: http://latrine.dgx.cz/ text odkazu .(title)[class]{style}
        if ($texy->allowed['link/definition']) {
            $text = preg_replace_callback(
                '#^\[([^\[\]\#\?\*\n]+)\]: +(\S+)(\ .+)?'.TEXY_MODIFIER.'?\s*()$#mUu',
                array($this, 'patternReferenceDef'),
                $text
            );
        }
    }



    /**
     * Callback for: [la trine]: http://latrine.dgx.cz/ text odkazu .(title)[class]{style}
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
            return $tx->invokeAroundHandlers('newReference', $parser, array($name));
        }

        $link->type = TEXY_LINK_BRACKET;

        if ($link->label != '') {  // NULL or ''
            // prevent deadlock
            if (isset($GLOBALS['TexyLinkModule::$deadlock'][$link->name])) {
                $content = $link->label;
            } else {
                $GLOBALS['TexyLinkModule::$deadlock'][$link->name] = TRUE;
                $el = TexyHtml::el();
                $lineParser = new TexyLineParser($tx, $el);
                $lineParser->parse($link->label);
                $content = $el->toString($tx);
                unset($GLOBALS['TexyLinkModule::$deadlock'][$link->name]);
            }
        } else {
            $content = $this->textualUrl($link);
            $content = $this->texy->protect($content, TEXY_CONTENT_TEXTUAL);
        }

        return $tx->invokeAroundHandlers('linkReference', $parser, array($link, $content));
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

        return $this->texy->invokeAroundHandlers(
            $name === 'link/email' ? 'linkEmail' : 'linkURL',
            $parser,
            array($link)
        );
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
        $type = TEXY_LINK_COMMON;

        // [ref]
        if (strlen($dest)>1 && $dest{0} === '[' && $dest{1} !== '*') {
            $type = TEXY_LINK_BRACKET;
            $dest = substr($dest, 1, -1);
            $link = $this->getReference($dest);

        // [* image *]
        } elseif (strlen($dest)>1 && $dest{0} === '[' && $dest{1} === '*') {
            $type = TEXY_LINK_IMAGE;
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
            $link->URL = str_replace('%s', urlencode($tx->stringToText($label)), $link->URL);
        }
        $link->modifier->setProperties($mMod);
        $link->type = $type;
        return $link;
    }



    /**
     * Finish invocation
     *
     * @param TexyHandlerInvocation  handler invocation
     * @param TexyLink
     * @param TexyHtml|string
     * @return TexyHtml|string
     */
    function solve($invocation, $link, $content = NULL)
    {
        if ($link->URL == NULL) return $content;

        $tx = $this->texy;

        $el = TexyHtml::el('a');

        if (empty($link->modifier)) {
            $nofollow = $popup = FALSE;
        } else {
            $nofollow = isset($link->modifier->classes['nofollow']);
            $popup = isset($link->modifier->classes['popup']);
            unset($link->modifier->classes['nofollow'], $link->modifier->classes['popup']);
            $el->attrs['href'] = NULL; // trick - move to front
            $link->modifier->decorate($tx, $el);
        }

        if ($link->type === TEXY_LINK_IMAGE) {
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

        if ($content !== NULL) $el->add($content);

        $tx->summary['links'][] = $el->attrs['href'];

        return $el;
    }



    /**
     * Finish invocation
     *
     * @param TexyHandlerInvocation  handler invocation
     * @param TexyLink
     * @return TexyHtml|string
     */
    function solveUrlEmail($invocation, $link)
    {
        $content = $this->textualUrl($link);
        $content = $this->texy->protect($content, TEXY_CONTENT_TEXTUAL);
        return $this->solve(NULL, $link, $content);
    }



    /**
     * Finish invocation
     *
     * @param TexyHandlerInvocation  handler invocation
     * @param string
     * @return FALSE
     */
    function solveNewReference($invocation, $name)
    {
        // no change
        return FALSE;
    }



    /**
     * Checks and corrects $URL
     * @param TexyLink
     * @return void
     */
    function checkLink($link) /* private */
    {
        $link->raw = $link->URL;

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
    }



    /**
     * Returns textual representation of URL
     * @param TexyLink
     * @return string
     */
    function textualUrl($link) /* private */
    {
        $URL = $link->raw === NULL ? $link->URL : $link->raw;

        if (preg_match('#^'.TEXY_EMAIL.'$#i', $URL)) { // email
            return $this->texy->obfuscateEmail
                   ? str_replace('@', "&#64;<!---->", $URL)
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
                $res .=  (strlen($parts['path']) > 16 ? ("/\xe2\x80\xa6" . preg_replace('#^.*(.{0,12})$#U', '$1', $parts['path'])) : $parts['path']);

            if (isset($parts['query'])) {
                $res .= strlen($parts['query']) > 4 ? "?\xe2\x80\xa6" : ('?'.$parts['query']);
            } elseif (isset($parts['fragment'])) {
                $res .= strlen($parts['fragment']) > 4 ? "#\xe2\x80\xa6" : ('#'.$parts['fragment']);
            }
            return $res;
        }

        return $URL;
    }

}









/** @see TexyLink::$type */
define('TEXY_LINK_COMMON',  1);
define('TEXY_LINK_BRACKET', 2);
define('TEXY_LINK_IMAGE', 3);

/**
 * @package Texy
 */
class TexyLink extends TexyBase
{
    /** @var string  URL in resolved form */
    var $URL;

    /** @var string  URL as written in text */
    var $raw;

    /** @var TexyModifier */
    var $modifier;

    /** @var int  how was link created? */
    var $type = TEXY_LINK_COMMON;

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
        if ($this->modifier) {
            $this->modifier = clone ($this->modifier);
        }
    }

}
