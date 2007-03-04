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
 * Links module
 */
class TexyLinkModule extends TexyModule
{
    protected $default = array(
        'link/reference' => TRUE,
        'link/email' => TRUE,
        'link/url' => TRUE,
        'link/definition' => TRUE,
    );

    /** @var string  root of relative links */
    public $root = '';

    /** @var string  for example: 'this.href="mailto:"+this.href.match(/./g).reverse().slice(0,-7).join("")'; */
    public $emailOnClick;

    /** @var string image popup event */
    public $imageOnClick = 'return !popupImage(this.href)';  //

    /** @var string class 'popup' event */
    public $popupOnClick = 'return !popup(this.href)';

    /** @var bool  always use rel="nofollow" for absolute links? */
    public $forceNoFollow = FALSE;

    /** @var array link references */
    protected $references = array();

    /** @var array */
    static private $deadlock;



    public function init()
    {
        self::$deadlock = array();

        $tx = $this->texy;
        // [reference]
        $tx->registerLinePattern(
            array($this, 'patternReference'),
            '#('.TEXY_LINK_REF.')#U',
            'link/reference'
        );

        // direct url and email
        $tx->registerLinePattern(
            array($this, 'patternUrlEmail'),
            '#(?<=\s|^|\(|\[|\<|:)(?:https?://|www\.|ftp://|ftp\.)[a-z0-9.-][/a-z\d+\.~%&?@=_:;\#,-]+[/\w\d+~%?@=_\#]#iu',
            'link/url'
        );

        $tx->registerLinePattern(
            array($this, 'patternUrlEmail'),
            '#(?<=\s|^|\(|\[|\<|:)'.TEXY_EMAIL.'#i',
            'link/email'
        );
    }



    /**
     * Text preprocessing
     */
    public function preProcess($text)
    {
        // [la trine]: http://www.dgx.cz/trine/ text odkazu .(title)[class]{style}
        if ($this->texy->allowed['link/definition'])
            return preg_replace_callback(
                '#^\[([^\[\]\#\?\*\n]+)\]: +(\S+)(\ .+)?'.TEXY_MODIFIER.'?()$#mU',
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
    private function patternReferenceDef($matches)
    {
        list(, $mRef, $mLink, $mLabel, $mMod) = $matches;
        //    [1] => [ (reference) ]
        //    [2] => link
        //    [3] => ...
        //    [4] => .(title)[class]{style}

        $mod = new TexyModifier($mMod);
        $this->addReference($mRef, $mLink, trim($mLabel), $mod);
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
    public function patternReference($parser, $matches)
    {
        list($match, $mRef) = $matches;
        //    [1] => [ref]

        $tx = $this->texy;
        $name = substr($mRef, 1, -1);
        $link = $this->getReference($name);

        if (!$link) {
            // try handler
            if (is_callable(array($tx->handler, 'newReference')))
                return $tx->handler->newReference($tx, $name);

            // no change
            return FALSE;
        }

        $link->type = TexyLink::REF;

        if ($link->label != '') {  // NULL or ''
            // prevent deadlock
            if (isset(self::$deadlock[$link->name])) {
                $content = $link->label;
            } else {
                self::$deadlock[$link->name] = TRUE;
                $lineParser = new TexyLineParser($tx);
                $content = $lineParser->parse($link->label);
                unset(self::$deadlock[$link->name]);
            }
        } else {
            $content = $this->textualURL($link->URL);
        }

        // event wrapper
        if (is_callable(array($tx->handler, 'linkReference'))) {
            $res = $tx->handler->linkReference($tx, $link, $content);
            if ($res !== NULL) return $res;
        }

        return $this->factory($link, $content);
    }



    /**
     * Callback for: http://www.dgx.cz   dave@dgx.cz
     *
     * @param TexyLineParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|string|FALSE
     */
    public function patternUrlEmail($parser, $matches, $name)
    {
        list($mURL) = $matches;
        //    [0] => URL

        $link = new TexyLink;
        $link->URL = $mURL;
        $content = $this->textualURL($mURL);

        // event wrapper
        $method = $name === 'link/email' ? 'linkEmail' : 'linkURL';
        if (is_callable(array($this->texy->handler, $method))) {
            $res = $this->texy->handler->$method($this->texy, $link, $content);
            if ($res !== NULL) return $res;
        }

        return $this->factory($link, $content);
    }





    /**
     * Adds new named reference
     *
     * @param string  reference name
     * @param string  URL
     * @param string  optional label
     * @param TexyModifier  optional modifier
     * @return void
     */
    public function addReference($name, $URL, $label=NULL, $modifier=NULL)
    {
        if (function_exists('mb_strtolower')) {
            $name = mb_strtolower($name, 'UTF-8');
        } else {
            $name = strtolower($name);
        }

        if (!$modifier) $modifier = new TexyModifier;

        $link = new TexyLink;
        $link->URL = $URL;
        $link->label = $label;
        $link->modifier = $modifier;
        $link->name = $name;
        $this->references[$name] = $link;
    }



    /**
     * Returns named reference
     *
     * @param string  reference name
     * @return TexyLink reference descriptor (or FALSE)
     */
    public function getReference($name)
    {
        if (function_exists('mb_strtolower')) {
            $name = mb_strtolower($name, 'UTF-8');
        } else {
            $name = strtolower($name);
        }

        if (isset($this->references[$name])) {
            return clone $this->references[$name];

        } else {
            $pos = strpos($name, '?');
            if ($pos === FALSE) $pos = strpos($name, '#');
            if ($pos !== FALSE) { // try to extract ?... #... part
                $name2 = substr($name, 0, $pos);
                if (isset($this->references[$name2])) {
                    $link = clone $this->references[$name2];
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
    public function parse($dest, $mMod, $label)
    {
        $tx = $this->texy;
        $type = TexyLink::NORMAL;

        // [ref]
        if (strlen($dest)>1 && $dest{0} === '[' && $dest{1} !== '*') {
            $type = TexyLink::REF;
            $dest = substr($dest, 1, -1);
            $link = $this->getReference($dest);

        // [* image *]
        } elseif (strlen($dest)>1 && $dest{0} === '[' && $dest{1} === '*') {
            $type = TexyLink::IMAGE;
            $dest = trim(substr($dest, 2, -2));
            $image = $tx->imageModule->getReference($dest);
            if ($image) {
                $link = new TexyLink;
                $link->URL = $image->linkedURL === NULL ? $image->imageURL : $image->linkedURL;
                $link->modifier = $image->modifier;
            }
        }

        if (empty($link)) {
            $link = new TexyLink;
            $link->URL = trim($dest);
            $link->modifier = new TexyModifier;
        }

        $link->URL = str_replace('%s', urlencode(Texy::wash($label)), $link->URL);
        $link->modifier->setProperties($mMod);
        $link->type = $type;
        return $link;
    }



    /**
     * Finish invocation
     *
     * @param TexyLink
     * @param TexyHtml|string
     * @return TexyHtml|string|FALSE
     */
    public function factory($link, $content=NULL)
    {
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
            $link->modifier->decorate($tx, $el);
        }


        if ($link->type === TexyLink::IMAGE || $link->type === TexyLink::AUTOIMAGE) {
            // image
            $el->href = Texy::completeURL($link->URL, $tx->imageModule->linkedRoot);
            $el->onclick = $this->imageOnClick;

        } else {
            if (preg_match('#^'.TEXY_EMAIL.'$#i', $link->URL)) {
                // email
                $el->href = 'mailto:' . $link->URL;
                $el->onclick = $this->emailOnClick;

            } else {
                // classic URL
                $el->href = Texy::completeURL($link->URL, $this->root, $isAbsolute);

                // rel="nofollow"
                if ($nofollow || ($this->forceNoFollow && $isAbsolute)) $el->rel[] = 'nofollow';
            }
        }

        // popup on click
        if ($popup) $el->onclick = $this->popupOnClick;

        if ($content !== NULL) $el->setContent($content);

        $tx->summary['links'][] = $el->href;

        return $el;
    }


    /**
     * Returns textual representation of URL
     * @param string
     * @return string
     */
    private function textualURL($URL)
    {
        if (preg_match('#^'.TEXY_EMAIL.'$#i', $URL)) { // email
            return $this->texy->obfuscateEmail
                   ? strtr($URL, array('@' => "&#160;(at)&#160;"))
                   : $URL;
        }

        if (preg_match('#^(https?://|ftp://|www\.|ftp\.|/)#i', $URL)) {

            if (strncasecmp($URL, 'www.', 4) === 0) $parts = @parse_url('none://'.$URL);
            elseif (strncasecmp($URL, 'ftp.', 4) === 0) $parts = @parse_url('none://'.$URL);
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



} // TexyLinkModule





class TexyLink
{
    const
        NORMAL = 1,
        REF = 2,
        IMAGE = 3,
        AUTOIMAGE = 4;

    public $URL;
    public $label;
    public $modifier;
    public $name;
    public $type = self::NORMAL;


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
