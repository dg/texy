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
    protected $allow = array('LinkReference', 'LinkEmail', 'LinkURL', 'LinkQuick', 'LinkDefinition');

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
    static private $callstack;



    public function init()
    {
        self::$callstack = array();

        $tx = $this->texy;
        $tx->registerLinePattern(
            $this,
            'processLineQuick',
            '#(['.TEXY_CHAR.'0-9@\#$%&.,_-]+)(?=:\[)'.TEXY_LINK.'()#Uu',
            'LinkQuick'
        );

        // [reference]
        $tx->registerLinePattern(
            $this,
            'processLineReference',
            '#('.TEXY_LINK_REF.')#U',
            'LinkReference'
        );

        // direct url and email
        $tx->registerLinePattern(
            $this,
            'processLineURL',
            '#(?<=\s|^|\(|\[|\<|:)(?:https?://|www\.|ftp://|ftp\.)[a-z0-9.-][/a-z\d+\.~%&?@=_:;\#,-]+[/\w\d+~%?@=_\#]#iu',
            'LinkURL'
        );

        $tx->registerLinePattern(
            $this,
            'processLineURL',
            '#(?<=\s|^|\(|\[|\<|:)'.TEXY_EMAIL.'#i',
            'LinkEmail'
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

        if (!$modifier) $modifier = new TexyModifier($this->texy);

        // if (strlen($URL) > 1)  if ($URL{0} === '\'' || $URL{0} === '"') $URL = substr($URL, 1, -1);
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

        if (isset($this->references[$name]))
            return $this->references[$name];

        $pos = strpos($name, '?');
        if ($pos === FALSE) $pos = strpos($name, '#');
        if ($pos !== FALSE) { // try to extract ?... #... part
            $name2 = substr($name, 0, $pos);
            if (isset($this->references[$name2])) {
                $ref = $this->references[$name2];
                $ref['URL'] .= substr($name, $pos);
                return $ref;
            }
        }

        return FALSE;
    }



    /**
     * Text preprocessing
     */
    public function preProcess($text)
    {
        // [la trine]: http://www.dgx.cz/trine/ text odkazu .(title)[class]{style}
        if ($this->texy->allowed['LinkDefinition'])
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

        $mod = new TexyModifier($this->texy);
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

        $el = $this->factory($mLink, NULL, NULL, NULL, $mContent);
        return $el->startMark($this->texy) . $mContent . $el->endMark($this->texy);
    }



    /**
     * Callback function: [ref]
     * @return string
     */
    public function processLineReference($parser, $matches)
    {
        list($match, $mRef) = $matches;
        //    [1] => [ref]

        $ref = $this->getReference(substr($mRef, 1, -1));
        if (!$ref) return $match;

        if ($ref['label']) {
            // prevent cycling
            if (isset(self::$callstack[$mRef['name']])) $content = $ref['label'];
            else {
                $label = new TexyTextualElement($this->texy);
                self::$callstack[$mRef['name']] = TRUE;
                $label->parse($ref['label']);
                $content = $label->content;
                unset(self::$callstack[$mRef['name']]);
            }
        } else {
            $link = new TexyUrl($ref['URL'], $this->root, TexyUrl::DIRECT);
            $content = $link->asTextual();
        }

        $el = $this->factory($mRef, NULL, NULL, NULL, NULL);
        return $el->startMark($this->texy) . $content . $el->endMark($this->texy);
    }



    /**
     * Callback function: http://www.dgx.cz
     * @return string
     */
    public function processLineURL($parser, $matches)
    {
        list($mURL) = $matches;
        //    [0] => URL

        $link = new TexyUrl($mURL, NULL, TexyUrl::DIRECT);
        $el = $this->factoryEl(
            $link,
            new TexyModifier($this->texy)
        );
        return $el->startMark($this->texy) . $link->asTextual() . $el->endMark($this->texy);
    }



    public function factory($dest, $mMod1, $mMod2, $mMod3, $label)
    {
        $src = TexyUrl::DIRECT;
        $root = $this->root;
        $tx = $this->texy;

        // [ref]
        if (strlen($dest)>1 && $dest{0} === '[' && $dest{1} !== '*') {
            $dest = substr($dest, 1, -1);
            $ref = $this->getReference($dest);
            if ($ref) {
                $dest = $ref['URL'];
                $modifier = clone $ref['modifier'];
            } else {
                $src = TexyUrl::REFERENCE;
                $modifier = new TexyModifier($tx);
            }

        // [* image *]
        } elseif (strlen($dest)>1 && $dest{0} === '[' && $dest{1} === '*') {
            $src = TexyUrl::IMAGE;
            $root = $tx->imageModule->linkedRoot;
            $dest = trim(substr($dest, 2, -2));
            $ref = $tx->imageModule->getReference($dest);
            if ($ref) {
                $dest = $ref['URL'];
                $modifier = clone $ref['modifier'];
            } else {
                $modifier = new TexyModifier($tx);
            }

        } else {
            $modifier = new TexyModifier($tx);
        }

        $modifier->setProperties($mMod1, $mMod2, $mMod3);

        $link = new TexyUrl($dest, $root, $src, $label);

        if (is_callable(array($tx->handler, 'Link')))
            $tx->handler->Link($link, $modifier);

        return $this->factoryEl($link, $modifier);
    }



    public function factoryEl($link, $modifier)
    {
        $classes = array_flip($modifier->classes);
        $nofollow = isset($classes['nofollow']);
        $popup = isset($classes['popup']);
        unset($classes['nofollow'], $classes['popup']);
        $modifier->classes = array_flip($classes);

        $el = $modifier->generate('a');
        $this->texy->summary['links'][] = $el->href = $link->asURL();

        // rel="nofollow"
        if ($nofollow) $el->rel[] = 'nofollow';

        // popup on click
        if ($popup) $el->onclick = $this->popupOnClick;

        // rel="nofollow"
        if (!$nofollow && $this->forceNoFollow && $link->isAbsolute()) $el->rel[] = 'nofollow';

        // email on click
        if ($link->isEmail()) $el->onclick = $this->emailOnClick;

        // image on click
        if ($link->isImage()) $el->onclick = $this->imageOnClick;

        return $el;
    }

} // TexyLinkModule
