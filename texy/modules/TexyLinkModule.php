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
    protected $allow = array('linkReference', 'linkEmail', 'linkURL', 'linkQuick', 'linkDefinition');

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
        $tx->registerLinePattern(
            array($this, 'processLineQuick'),
            '#(['.TEXY_CHAR.'0-9@\#$%&.,_-]+)(?=:\[)'.TEXY_LINK.'()#Uu',
            'linkQuick'
        );

        // [reference]
        $tx->registerLinePattern(
            array($this, 'processLineReference'),
            '#('.TEXY_LINK_REF.')#U',
            'linkReference'
        );

        // direct url and email
        $tx->registerLinePattern(
            array($this, 'processLineURL'),
            '#(?<=\s|^|\(|\[|\<|:)(?:https?://|www\.|ftp://|ftp\.)[a-z0-9.-][/a-z\d+\.~%&?@=_:;\#,-]+[/\w\d+~%?@=_\#]#iu',
            'linkURL'
        );

        $tx->registerLinePattern(
            array($this, 'processLineURL'),
            '#(?<=\s|^|\(|\[|\<|:)'.TEXY_EMAIL.'#i',
            'linkEmail'
        );
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

        $this->references[$name] = array(
            'URL' => $URL,
            'label' => $label,
            'modifier' => $modifier,
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

        if (isset($this->references[$name])) {
            $ref = $this->references[$name];

        } else {
            $pos = strpos($name, '?');
            if ($pos === FALSE) $pos = strpos($name, '#');
            if ($pos !== FALSE) { // try to extract ?... #... part
                $name2 = substr($name, 0, $pos);
                if (isset($this->references[$name2])) {
                    $ref = $this->references[$name2];
                    $ref['URL'] .= substr($name, $pos);
                }
            }
        }

        if (empty($ref)) return FALSE;

        $ref['modifier'] = empty($ref['modifier'])
            ? new TexyModifier
            : clone $ref['modifier'];

        return $ref;
    }



    /**
     * Text preprocessing
     */
    public function preProcess($text)
    {
        // [la trine]: http://www.dgx.cz/trine/ text odkazu .(title)[class]{style}
        if ($this->texy->allowed['linkDefinition'])
            return preg_replace_callback(
                '#^\[([^\[\]\#\?\*\n]+)\]: +(\S+)(\ .+)?'.TEXY_MODIFIER.'?()$#mU',
                array($this, 'processReferenceDefinition'),
                $text
            );
        return $text;
    }



    /**
     * Callback function: [la trine]: http://www.dgx.cz/trine/ text odkazu .(title)[class]{style}
     * @return string
     */
    public function processReferenceDefinition($matches)
    {
        list(, $mRef, $mLink, $mLabel, $mMod1, $mMod2, $mMod3) = $matches;
        //    [1] => [ (reference) ]
        //    [2] => link
        //    [3] => ...
        //    [4] => (title)
        //    [5] => [class]
        //    [6] => {style}

        $mod = new TexyModifier;
        $mod->setProperties($mMod1, $mMod2, $mMod3);
        $this->addReference($mRef, $mLink, trim($mLabel), $mod);
        return '';
    }



    /**
     * Callback function: ....:LINK
     * @return string
     */
    public function processLineQuick($parser, $matches)
    {
        list(, $mContent, $mLink) = $matches;
        //    [1] => ...
        //    [2] => [ref]

        $tx = $this->texy;
        $req = $this->parse($mLink, NULL, NULL, NULL, $mContent);
        $el = $this->factory($req);
        $el->addChild($mContent);

        if (is_callable(array($tx->handler, 'link')))
            $tx->handler->link($tx, $req, $el);

        $tx->summary['links'][] = $el->href;
        return $el;
    }



    /**
     * Callback function: [ref]
     * @return string
     */
    public function processLineReference($parser, $matches)
    {
        list($match, $mRef) = $matches;
        //    [1] => [ref]

        $tx = $this->texy;
        $name = substr($mRef, 1, -1);
        $ref = $this->getReference($name);

        if (!$ref) {
            // try handler
            if (is_callable(array($tx->handler, 'reference')))
                return $tx->handler->reference($tx, $name);

            // no change
            return FALSE;
        }

        if ($ref['label']) {
            // prevent deadlock
            if (isset(self::$deadlock[$mRef['name']])) {
                $content = $ref['label'];
            } else {
                $label = new TexyTextualElement($tx);
                self::$deadlock[$mRef['name']] = TRUE;
                $label->parse($ref['label']);
                $content = $label->content;
                unset(self::$deadlock[$mRef['name']]);
            }
        } else {
            $content = $this->textualURL($ref['URL']);
        }

        $el = $this->factory($ref);
        $el->addChild($content);

        if (is_callable(array($tx->handler, 'reference2')))
            $tx->handler->reference2($tx, $ref, $el);

        $tx->summary['links'][] = $el->href;
        return $el;
    }



    /**
     * Callback function: http://www.dgx.cz   dave@dgx.cz
     * @return string
     */
    public function processLineURL($parser, $matches, $name)
    {
        list($mURL) = $matches;
        //    [0] => URL

        $tx = $this->texy;
        $req = array(
            'URL' => $mURL,
        );
        $el = $this->factory($req);
        $el->addChild($this->textualURL($mURL));

        if (is_callable(array($tx->handler, $name)))
            $tx->handler->$name($tx, $mURL, $el);

        $tx->summary['links'][] = $el->href;
        return $el;
    }



    public function parse($dest, $mMod1, $mMod2, $mMod3, $label)
    {
        $tx = $this->texy;
        $image = FALSE;

        // [ref]
        if (strlen($dest)>1 && $dest{0} === '[' && $dest{1} !== '*') {
            $dest = substr($dest, 1, -1);
            $req = $this->getReference($dest);

        // [* image *]
        } elseif (strlen($dest)>1 && $dest{0} === '[' && $dest{1} === '*') {
            $image = TRUE;
            $dest = trim(substr($dest, 2, -2));
            $reqI = $tx->imageModule->getReference($dest);
            if ($reqI) {
                $req['URL'] = empty($reqI['linkedURL']) ? $reqI['imageURL'] : $reqI['linkedURL'];
                $req['modifier'] = $reqI['modifier'];
            }
        }

        if (empty($req)) {
            $req = array(
                'URL' => trim($dest),
                'label' => $label,
                'modifier' => new TexyModifier,
            );
        }

        $req['URL'] = str_replace('%s', urlencode(Texy::wash($label)), $req['URL']);
        $req['modifier']->setProperties($mMod1, $mMod2, $mMod3);
        $req['image'] = $image;
        return $req;
    }



    public function factory($req)
    {
        extract($req);
        $tx = $this->texy;

        if (empty($modifier)) {
            $el = TexyHtml::el('a');
            $nofollow = $popup = FALSE;
        } else {
            $classes = array_flip($modifier->classes);
            $nofollow = isset($classes['nofollow']);
            $popup = isset($classes['popup']);
            unset($classes['nofollow'], $classes['popup']);
            $modifier->classes = array_flip($classes);
            $el = $modifier->generate($tx, 'a');
        }


        if (empty($image)) {

            if (preg_match('#^'.TEXY_EMAIL.'$#i', $URL)) {
                // email
                $el->href = 'mailto:' . $URL;
                $el->onclick = $this->emailOnClick;

            } else {
                // classic URL
                $el->href = Texy::completeURL($URL, $this->root, $isAbsolute);

                // rel="nofollow"
                if ($nofollow || ($this->forceNoFollow && $isAbsolute)) $el->rel[] = 'nofollow';
            }

        } else {
            // image
            $el->href = Texy::completeURL($URL, $tx->imageModule->linkedRoot);
            $el->onclick = $this->imageOnClick;
        }

        // popup on click
        if ($popup) $el->onclick = $this->popupOnClick;

        return $el;
    }



    /**
     * Returns textual representation of URL
     * @return string
     */
    public function textualURL($URL)
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
