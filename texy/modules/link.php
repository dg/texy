<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    $Revision$ $Date$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();





/**
 * LINKS MODULE CLASS
 */
class TexyLinkModule extends TexyModule
{
    /** @var callback    Callback that will be called with newly created element */
    public $handler;

    // options
    public $allowed;
    public $root            = '';                          // root of relative links
    public $emailOnClick    = NULL;                        // 'this.href="mailto:"+this.href.match(/./g).reverse().slice(0,-7).join("")';
    public $imageOnClick    = 'return !popupImage(this.href)';  // image popup event
    public $popupOnClick    = 'return !popup(this.href)';  // popup popup event
    public $forceNoFollow   = FALSE;                       // always use rel="nofollow" for absolute links




    public function __construct($texy)
    {
        parent::__construct($texy);

        $this->allowed = (object) NULL;
        $this->allowed->link      = TRUE;   // classic link "xxx":URL and [reference]
        $this->allowed->email     = TRUE;   // emails replacement
        $this->allowed->url       = TRUE;   // direct url replacement
        $this->allowed->quickLink = TRUE;   // quick link xxx:[url] or [reference]
        $this->allowed->references = TRUE;  // [ref]: URL  reference definitions
    }





    /**
     * Module initialization.
     */
    public function init()
    {
        if ($this->allowed->quickLink)
            $this->texy->registerLinePattern(
                $this,
                'processLineQuick',
                '#([:CHAR:0-9@\#$%&.,_-]+)(?=:\[)<LINK>()#U<UTF>'
            );

        // [reference]
        $this->texy->registerLinePattern(
            $this,
            'processLineReference',
            '#('.TEXY_PATTERN_LINK_REF.')#U'
        );

        // direct url and email
        if ($this->allowed->url)
            $this->texy->registerLinePattern(
                $this,
                'processLineURL',
                '#(?<=\s|^|\(|\[|\<|:)(?:https?://|www\.|ftp://|ftp\.)[a-z0-9.-][/a-z\d+\.~%&?@=_:;\#,-]+[/\w\d+~%?@=_\#]#i<UTF>'
            );

        if ($this->allowed->email)
            $this->texy->registerLinePattern(
                $this,
                'processLineURL',
                '#(?<=\s|^|\(|\[|\<|:)'.TEXY_PATTERN_EMAIL.'#i'
            );
    }





    /**
     * Add new named image
     */
    public function addReference($name, $obj)
    {
        $this->texy->addReference($name, $obj);
    }




    /**
     * Receive new named link. If not exists, try
     * call user function to create one.
     */
    public function getReference($refName) {
        $el = $this->texy->getReference($refName);
        $query = '';

        if (!$el) {
            $queryPos = strpos($refName, '?');
            if ($queryPos === FALSE) $queryPos = strpos($refName, '#');
            if ($queryPos !== FALSE) { // try to extract ?... #... part
                $el = $this->texy->getReference(substr($refName, 0, $queryPos));
                $query = substr($refName, $queryPos);
            }
        }

        if (!($el instanceof TexyLinkReference)) return FALSE;

        $el->query = $query;
        return $el;
    }



    /**
     * Preprocessing
     */
    public function preProcess($text)
    {
        // [la trine]: http://www.dgx.cz/trine/ text odkazu .(title)[class]{style}
        if ($this->allowed->references)
            return preg_replace_callback(
                '#^\[([^\[\]\#\?\*\n]+)\]: +('.TEXY_PATTERN_LINK_IMAGE.'|(?!\[)\S+)(\ .+)?'.TEXY_PATTERN_MODIFIER.'?()$#mU',
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


        $elRef = new TexyLinkReference($this->texy, $mLink, $mLabel);
        $elRef->modifier->setProperties($mMod1, $mMod2, $mMod3);

        $this->addReference($mRef, $elRef);

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

        if (!$this->allowed->quickLink) return $mContent;

        $elLink = new TexyLinkElement($this->texy);
        $elLink->setLinkRaw($mLink, $mContent);
        return $parser->element->appendChild($elLink, $mContent);
    }





    /**
     * Callback function: [ref]
     * @return string
     */
    public function processLineReference($parser, $matches)
    {
        list($match, $mRef) = $matches;
        //    [1] => [ref]

        if (!$this->allowed->link) return $match;

        $elLink = new TexyLinkRefElement($this->texy);
        if ($elLink->setLink($mRef) === FALSE) return $match;

        return $parser->element->appendChild($elLink);
    }




    /**
     * Callback function: http://www.dgx.cz
     * @return string
     */
    public function processLineURL($parser, $matches)
    {
        list($mURL) = $matches;
        //    [0] => URL

        $elLink = new TexyLinkElement($this->texy);
        $elLink->setLinkRaw($mURL);
        return $parser->element->appendChild($elLink, $elLink->link->asTextual());
    }





} // TexyLinkModule






class TexyLinkReference {
    public $URL;
    public $query;
    public $label;
    public $modifier;


    // constructor
    public function __construct($texy, $URL = NULL, $label = NULL)
    {
        $this->modifier = new TexyModifier($texy);

        if (strlen($URL) > 1)  if ($URL{0} == '\'' || $URL{0} == '"') $URL = substr($URL, 1, -1);
        $this->URL = trim($URL);
        $this->label = trim($label);
    }

}







/**
 * HTML TAG ANCHOR
 */
class TexyLinkElement extends TexyInlineTagElement
{
    public $link;


    // constructor
    public function __construct($texy)
    {
        parent::__construct($texy);

        $this->link = new TexyUrl($texy);
    }


    public function setLink($URL)
    {
        $this->link->set($URL, $this->texy->linkModule->root);
    }


    public function setLinkRaw($link, $text='')
    {
        if (strlen($link)>1 && $link{0} == '[' && $link{1} != '*') {
            $elRef = $this->texy->linkModule->getReference( substr($link, 1, -1) );

            if ($elRef) {
                $this->modifier->copyFrom($elRef->modifier);
                $link = $elRef->URL . $elRef->query;
                $link = str_replace('%s', urlencode(Texy::wash($text)), $link);

            } else {
                $this->setLink(substr($link, 1, -1));
                return;
            }
        }

        if (strlen($link)>1 && $link{0} == '[' && $link{1} == '*') {
            $elImage = new TexyImageElement($this->texy);
            $elImage->setImagesRaw(substr($link, 2, -2));
            $elImage->requireLinkImage();
            $this->link->copyFrom($elImage->linkImage);
            return;
        }

        $this->setLink($link);
    }




    protected function generateTags(&$tags)
    {
        if ($this->link->asURL() == '') return;  // dest URL is required

        $attrs = $this->modifier->getAttrs('a');
        $this->texy->summary['links'][] = $attrs['href'] = $this->link->asURL();

        // rel="nofollow"
        // rel="nofollow"
        $nofollowClass = in_array('nofollow', $this->modifier->unfilteredClasses);
        if ($this->link->isAbsolute() && ($this->texy->linkModule->forceNoFollow || $nofollowClass))
            $attrs['rel'] = 'nofollow'; // TODO: append, not replace

        $attrs['id']    = $this->modifier->id;
        $attrs['title'] = $this->modifier->title;
        $attrs['class'] = $this->modifier->classes;
        $attrs['style'] = $this->modifier->styles;
        if ($nofollowClass) {
            if (($pos = array_search('nofollow', $attrs['class'])) !== FALSE)
                 unset($attrs['class'][$pos]);
        }

        // popup on click
        $popup = in_array('popup', $this->modifier->unfilteredClasses);
        if ($popup) {
            if (($pos = array_search('popup', $attrs['class'])) !== FALSE)
                 unset($attrs['class'][$pos]);
            $attrs['onclick'] = $this->texy->linkModule->popupOnClick;
        }


        // email on click
        if ($this->link->isEmail())
            $attrs['onclick'] = $this->texy->linkModule->emailOnClick;

        // image on click
        if ($this->link->isImage())
            $attrs['onclick'] = $this->texy->linkModule->imageOnClick;

        $tags['a'] = $attrs;
    }


} // TexyLinkElement









/**
 * HTML ELEMENT ANCHOR (with content)
 */
class TexyLinkRefElement extends TexyTextualElement
{
    public $refName;
    public $contentType = TexyDomElement::CONTENT_TEXTUAL;


    static private $callstack;


    public function setLink($refRaw)
    {
        $this->refName = substr($refRaw, 1, -1);
        $lowName = strtolower($this->refName); // pozor na UTF8 !
        // prevent cycling
        if (isset(self::$callstack[$lowName])) return FALSE;

        $elRef =  $this->texy->linkModule->getReference($this->refName);
        if (!$elRef) return FALSE;

        $elLink = new TexyLinkElement($this->texy);
        $elLink->setLinkRaw($refRaw);

        if ($elRef->label) {
            self::$callstack[$lowName] = TRUE;
            $this->parse($elRef->label);
            unset(self::$callstack[$lowName]);

        } else {
            $this->setContent($elLink->link->asTextual(), TRUE);
        }

        $this->setContent($this->appendChild($elLink, $this->content), TRUE);
    }




} // TexyLinkRefElement