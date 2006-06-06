<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://www.texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 * @package    Texy
 * @category   Text
 * @version    1.1 for PHP4 & PHP5 $Date$ $Revision$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();





/**
 * LINKS MODULE CLASS
 */
class TexyLinkModule extends TexyModule {
    // options
    var $allowed;
    var $root            = '';                          // root of relative links
    var $emailOnClick    = null;                        // 'this.href="mailto:"+this.href.match(/./g).reverse().slice(0,-7).join("")';
    var $imageOnClick    = 'return !popupImage(this.href)';  // image popup event
    var $popupOnClick    = 'return !popup(this.href)';  // popup popup event
    var $forceNoFollow   = false;                       // always use rel="nofollow" for absolute links




    // constructor
    function TexyLinkModule(&$texy)
    {
        parent::__construct($texy);

        $this->allowed->link      = true;   // classic link "xxx":url and [reference]
        $this->allowed->email     = true;   // emails replacement
        $this->allowed->url       = true;   // direct url replacement
        $this->allowed->quickLink = true;   // quick link xxx:[url] or [reference]
        $this->allowed->references = true;  // [ref]: URL  reference definitions
    }



    /**
     * Module initialization.
     */
    function init()
    {
        Texy::adjustDir($this->root);

        if ($this->allowed->quickLink)
            $this->registerLinePattern('processLineQuick',   '#([:CHAR:0-9@\#$%&.,_-]+)(?=:\[)<LINK>()#U<UTF>');

        // [reference]
        $this->registerLinePattern('processLineReference',   '#('.TEXY_PATTERN_LINK_REF.')#U');

        // direct url and email
        if ($this->allowed->url)
            $this->registerLinePattern('processLineURL',     '#(?<=\s|^|\(|\[|\<|:)(?:https?://|www\.|ftp://|ftp\.)[a-z0-9.-][/a-z\d+\.~%&?@=_:;\#,-]+[/\w\d+~%?@=_\#]#i<UTF>');
        if ($this->allowed->email)
            $this->registerLinePattern('processLineURL',     '#(?<=\s|^|\(|\[|\<|:)'.TEXY_PATTERN_EMAIL.'#i');
    }





    /**
     * Add new named image
     */
    function addReference($name, &$obj)
    {
        $this->texy->addReference($name, $obj);
    }




    /**
     * Receive new named link. If not exists, try
     * call user function to create one.
     */
    function &getReference($refName) {
        $el = & $this->texy->getReference($refName);
        $query = '';

        if (!$el) {
            $queryPos = strpos($refName, '?');
            if ($queryPos === false) $queryPos = strpos($refName, '#');
            if ($queryPos !== false) { // try to extract ?... #... part
                $el = & $this->texy->getReference(substr($refName, 0, $queryPos));
                $query = substr($refName, $queryPos);
            }
        }

        $false = false; // php4_sucks
        if (!is_a($el, 'TexyLinkReference')) return $false;

        $el->query = $query;
        return $el;
    }



    /**
     * Preprocessing
     */
    function preProcess(&$text)
    {
        // [la trine]: http://www.dgx.cz/trine/ text odkazu .(title)[class]{style}
        if ($this->allowed->references)
            $text = preg_replace_callback('#^\[([^\[\]\#\?\*\n]+)\]: +('.TEXY_PATTERN_LINK_IMAGE.'|(?!\[)\S+)(\ .+)?'.TEXY_PATTERN_MODIFIER.'?()$#mU', array(&$this, 'processReferenceDefinition'), $text);
    }




    /**
     * Callback function: [la trine]: http://www.dgx.cz/trine/ text odkazu .(title)[class]{style}
     * @return string
     */
    function processReferenceDefinition(&$matches)
    {
        list($match, $mRef, $mLink, $mLabel, $mMod1, $mMod2, $mMod3) = $matches;
        //    [1] => [ (reference) ]
        //    [2] => link
        //    [3] => ...
        //    [4] => (title)
        //    [5] => [class]
        //    [6] => {style}

        $elRef = &new TexyLinkReference($this->texy, $mLink, $mLabel);
        $elRef->modifier->setProperties($mMod1, $mMod2, $mMod3);

        $this->addReference($mRef, $elRef);

        return '';
    }








    /**
     * Callback function: ....:LINK
     * @return string
     */
    function processLineQuick(&$lineParser, &$matches)
    {
        list($match, $mContent, $mLink) = $matches;
        //    [1] => ...
        //    [2] => [ref]

        if (!$this->allowed->quickLink) return $mContent;

        $elLink = &new TexyLinkElement($this->texy);
        $elLink->setLinkRaw($mLink);
        return $lineParser->element->appendChild($elLink, $mContent);
    }





    /**
     * Callback function: [ref]
     * @return string
     */
    function processLineReference(&$lineParser, &$matches)
    {
        list($match, $mRef) = $matches;
        //    [1] => [ref]

        if (!$this->allowed->link) return $match;

        $elLink = &new TexyLinkRefElement($this->texy);
        if ($elLink->setLink($mRef) === false) return $match;

        return $lineParser->element->appendChild($elLink);
    }




    /**
     * Callback function: http://www.dgx.cz
     * @return string
     */
    function processLineURL(&$lineParser, &$matches)
    {
        list($mURL) = $matches;
        //    [0] => URL

        $elLink = &new TexyLinkElement($this->texy);
        $elLink->setLinkRaw($mURL);
        return $lineParser->element->appendChild($elLink, $elLink->link->toString());
    }





} // TexyLinkModule






class TexyLinkReference {
    var $URL;
    var $query;
    var $label;
    var $modifier;


    // constructor
    function TexyLinkReference(&$texy, $URL = null, $label = null)
    {
        $this->modifier = & $texy->createModifier();

        if (strlen($URL) > 1)  if ($URL{0} == '\'' || $URL{0} == '"') $URL = substr($URL, 1, -1);
        $this->URL = trim($URL);
        $this->label = trim($label);
    }

}






/***************************************************************************
                                                             TEXY! DOM ELEMENTS                          */



/**
 * HTML TAG ANCHOR
 */
class TexyLinkElement extends TexyInlineTagElement {
    var $link;
    var $nofollow = false;


    // constructor
    function __construct(&$texy)
    {
        parent::__construct($texy);

        $this->link = & $texy->createURL();
        $this->link->root = $texy->linkModule->root;
    }


    function setLink($URL)
    {
        $this->link->set($URL);
    }


    function setLinkRaw($link)
    {
        if (@$link{0} == '[' && @$link{1} != '*') {
            $elRef = & $this->texy->linkModule->getReference( substr($link, 1, -1) );
            if ($elRef) {
                $this->modifier->copyFrom($elRef->modifier);
                $link = $elRef->URL . $elRef->query;

            } else {
                $this->setLink(substr($link, 1, -1));
                return;
            }
        }

        $l = strlen($link);
        if (@$link{0} == '[' && @$link{1} == '*') {
            $elImage = &new TexyImageElement($this->texy);
            $elImage->setImagesRaw(substr($link, 2, -2));
            $elImage->requireLinkImage();
            $this->link->copyFrom($elImage->linkImage);
            return;
        }

        $this->setLink($link);
    }




    function generateTags(&$tags)
    {
        if ($this->link->URL == null) return;  // dest URL is required

        $attrs = $this->modifier->getAttrs('a');
        $this->texy->summary->links[] = $attrs['href'] = $this->link->URL;

        // rel="nofollow"
        $nofollowClass = in_array('nofollow', $this->modifier->unfilteredClasses);
        if (($this->link->type & TEXY_URL_ABSOLUTE) && ($nofollowClass || $this->nofollow || $this->texy->linkModule->forceNoFollow))
            $attrs['rel'] = 'nofollow';

        $attrs['id']    = $this->modifier->id;
        $attrs['title'] = $this->modifier->title;
        $attrs['class'] = $this->modifier->classes;
        $attrs['style'] = $this->modifier->styles;
        if ($nofollowClass) {
            if (($pos = array_search('nofollow', $attrs['class'])) !== false)
                 unset($attrs['class'][$pos]);
        }

        // popup on click
        $popup = in_array('popup', $this->modifier->unfilteredClasses);
        if ($popup) {
            if (($pos = array_search('popup', $attrs['class'])) !== false)
                 unset($attrs['class'][$pos]);
            $attrs['onclick'] = $this->texy->linkModule->popupOnClick;
        }


        // email on click
        if ($this->link->type & TEXY_URL_EMAIL)
            $attrs['onclick'] = $this->texy->linkModule->emailOnClick;

        // image on click
        if ($this->link->type & TEXY_URL_IMAGE_LINKED)
            $attrs['onclick'] = $this->texy->linkModule->imageOnClick;

        $tags['a'] = $attrs;
    }


} // TexyLinkElement









/**
 * HTML ELEMENT ANCHOR (with content)
 */
class TexyLinkRefElement extends TexyTextualElement {
    var $refName;
    var $contentType = TEXY_CONTENT_TEXTUAL;





    function setLink($refRaw)
    {
        $this->refName = substr($refRaw, 1, -1);
        $elRef = & $this->texy->linkModule->getReference($this->refName);
        if (!$elRef) return false;

        $this->texy->_preventCycling = true;
        $elLink = &new TexyLinkElement($this->texy);
        $elLink->setLinkRaw($refRaw);

        if ($elRef->label) {
            $this->parse($elRef->label);
        } else {
            $this->setContent($elLink->link->toString(), true);
        }

        $this->content = $this->appendChild($elLink, $this->content);
        $this->texy->_preventCycling = false;
    }




} // TexyLinkRefElement





?>