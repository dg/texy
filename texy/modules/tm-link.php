<?php

/**
 * --------------------------------
 *   LINKS - TEXY! DEFAULT MODULE
 * --------------------------------
 *
 * Version 1 Release Candidate
 *
 * Copyright (c) 2004-2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * LINKS MODULE CLASS
 */
class TexyLinkModule extends TexyModule {
  // options
  var $allowed         = true;                         // generally disable / enable images
  var $root            = '';                          // root of relative links
  var $emailOnClick    = '';                          // 'this.href="mailto:"+this.href.match(/./g).reverse().slice(0,-7).join("")';
  var $imageOnClick    = 'return !popup(this.href)';  // image popup event
  var $forceNoFollow   = false;                       // always use rel="nofollow" for absolute links

  // private
  var $references      = array();                     // references: 'home' => TexyLinkReference
  var $userReferences;                                // function &myUserFunc(&$texy, $refName): returns TexyLinkReference (or false)
  var $imageModuleName = 'images';                    // $texy->modules[NAME]
  var $_disableRefs    = false;                       // prevent recurse calling
  var $_backupReferences;


  /***
   * Module initialization.
   */
  function init()
  {
    // "... .(title)[class]{style}":LINK    where LINK is:   url | [ref] | [*image*]
    $this->registerLinePattern('processLineQuot',      '#(?<!\")\"(?!\ )([^\n\"]+)MODIFIER?(?<!\ )\"'.TEXY_PATTERN_LINK.'()#U');
    $this->registerLinePattern('processLineQuot',      '#(?<!\~)\~(?!\ )([^\n\~]+)MODIFIER?(?<!\ )\~'.TEXY_PATTERN_LINK.'()#U');

    // [ref]
    $this->registerLinePattern('processLineReference', '#('.TEXY_PATTERN_LINK_REF.')#U');

    $this->registerLinePattern('processLineURL',       '#(?<=\s|^|\(|\[|\<|:)(?:https?://|www\.|ftp://|ftp\.)[a-z0-9.-][/a-z\d+\.~%&?@=_:;\#,-]+[/\w\d+~%?@=_\#]#i'.TEXY_PATTERN_UTF);
    $this->registerLinePattern('processLineURL',       '#(?<=\s|^|\(|\[|\<|:)'.TEXY_PATTERN_EMAIL.'#i');

    Texy::adjustDir($this->root);
  }




  /***
   * Add new named link
   */
  function addReference($name, &$obj)
  {
    $name = strtolower($name);
    $this->references[$name] = &$obj;
  }




  /***
   * Receive new named link. If not exists, try
   * call user function to create one.
   */
  function &getReference($name)
  {
    if ($this->_disableRefs) return false;

    $name = strtolower($name);

    if (isset($this->references[$name]))
      return $this->references[$name];


    $queryPos = strpos($name, '?');
    if ($queryPos === false) $queryPos = strpos($name, '#');
    if ($queryPos !== false) { // try to extract ?... #... part
      $nameX = substr($name, 0, $queryPos);

      if (isset($this->references[$nameX])) {
        $obj = clone ($this->references[$nameX]);
        unset($obj->modifier); // for PHP4
        $obj->modifier = clone ($this->references[$nameX]->modifier);
        $obj->URL .= substr($name, $queryPos);
        return $obj;
      }
    }

    if ($this->userReferences) {
      $this->_disableRefs = true;
      $obj = &call_user_func_array(
                   $this->userReferences,
                   array(&$this->texy, $name)
      );
      $this->_disableRefs = false;

      if ($obj) {
        $this->references[$name] = & $obj; // save for next time
        return $obj;
      }
    }

    return false;
  }




  /***
   * Forget all references created during last parse()
   */
  function forgetReferences()
  {
    $this->references = $this->_backupReferences;
  }



  /***
   * Preprocessing
   */
  function preProcess(&$text)
  {
    $this->_backupReferences = $this->references;

    // [la trine]: http://www.dgx.cz/trine/ text odkazu .(title)[class]{style}
    $text = preg_replace_callback('#^\[([^\[\]\#\?\*\n]+)\]: +('.TEXY_PATTERN_LINK_IMAGE.'|(?-U)(?!\[)\S+(?U))(\ .+)?\ *'.TEXY_PATTERN_MODIFIER.'?()$#mU', array(&$this, '_replaceReference'), $text);
  }




  /***
   * Callback function: [la trine]: http://www.dgx.cz/trine/ text odkazu .(title)[class]{style}
   * @return string
   */
  function _replaceReference(&$matches)
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






  /***
   * Callback function: ".... (title)[class]{style}<>":LINK
   * @return string
   */
  function processLineQuot(&$lineParser, &$matches)
  {
    list($match, $mContent, $mMod1, $mMod2, $mMod3, $mLink) = $matches;
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => url | [ref] | [*image*]

    if (!$this->allowed) return $mContent;

    $elLink = &new TexyLinkElement($this->texy);
    $elLink->setLinkRaw($mLink);
    $elLink->modifier->setProperties($mMod1, $mMod2, $mMod3);
    return $elLink->addTo($lineParser->element, $mContent);
  }





  /***
   * Callback function: [ref]
   * @return string
   */
  function processLineReference(&$lineParser, &$matches)
  {
    list($match, $mRef) = $matches;
    //    [1] => [ref]

    if (!$this->allowed) return $match;

    $elLink = &new TexyLinkRefElement($this->texy);
    if ($elLink->setLink($mRef) === false) return $match;

    return $elLink->addTo($lineParser->element);
  }




  /***
   * Callback function: http://www.dgx.cz
   * @return string
   */
  function processLineURL(&$lineParser, &$matches)
  {
    list($mURL) = $matches;
    //    [0] => URL

    if (!$this->allowed) return $mURL;

    $elLink = &new TexyLinkElement($this->texy);
    $elLink->setLinkRaw($mURL);
    return $elLink->addTo($lineParser->element, $elLink->link->toString());
  }




} // TexyLinkModule






class TexyLinkReference {
  var $URL;
  var $label;
  var $modifier;


  // constructor
  function TexyLinkReference(&$texy, $URL = null, $label = null)
  {
    $this->modifier = & $texy->createModifier();

    if (strlen($URL) > 1)  if ($URL{0} == '\'' || $URL{0} == '"') $URL = substr($URL, 1, -1);
    $this->URL = $URL;
    $this->label = $label;
  }

}






/****************************************************************************
                               TEXY! DOM ELEMENTS                          */



/**
 * HTML TAG ANCHOR
 */
class TexyLinkElement extends TexyInlineTagElement {
  var $parentModule;
  var $link;
  var $nofollow = false;


  // constructor
  function TexyLinkElement(&$texy)
  {
    parent::TexyInlineTagElement($texy);
    $this->parentModule = & $texy->modules['TexyLinkModule'];

    $this->link = & $texy->createURL();
    $this->link->root = $this->parentModule->root;
  }


  function setLink($URL)
  {
    $this->link->set($URL);
  }


  function setLinkRaw($link)
  {
    if (@$link{0} == '[' && @$link{1} != '*') {
      $elRef = & $this->parentModule->getReference( substr($link, 1, -1) );
      if ($elRef) {
        $this->modifier->copyFrom($elRef->modifier);
        $link = $elRef->URL;

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




  function generateTag(&$tag, &$attr)
  {
    if (!$this->link->URL) return;  // image URL is required

    $tag  = 'a';

    $this->texy->summary->links[] = $attr['href'] = $this->link->URL;

    // rel="nofollow"
    $nofollowClass = in_array('nofollow', $this->modifier->unfilteredClasses);
    if (($this->link->type & TEXY_URL_ABSOLUTE) && ($nofollowClass || $this->nofollow || $this->parentModule->forceNoFollow))
      $attr['rel'] = 'nofollow';

    $attr['id']    = $this->modifier->id;
    $attr['title'] = $this->modifier->title;
    $classes = $this->modifier->classes;
    if ($nofollowClass) {
      $nofollowClass = array_search('nofollow', $classes);
      if ($nofollowClass !== false) unset($classes[$nofollowClass]);
    }
    $attr['class'] = TexyModifier::implodeClasses($classes);

    $styles = $this->modifier->styles;
    $attr['style'] = TexyModifier::implodeStyles($styles);

    // email on click
    if ($this->link->type & TEXY_URL_EMAIL)
      $attr['onclick'] = $this->parentModule->emailOnClick;

    // image on click
    if ($this->link->type & TEXY_URL_IMAGE_LINKED)
      $attr['onclick'] = $this->parentModule->imageOnClick;
  }


} // TexyLinkElement









/**
 * HTML ELEMENT ANCHOR (with content)
 */
class TexyLinkRefElement extends TexyTextualElement {
  var $parentModule;
  var $tag = 'a';
  var $link;
  var $nofollow = false;
  var $refName;
  var $contentType = TEXY_CONTENT_TEXTUAL;

  // private


  // constructor
  function TexyLinkRefElement(&$texy)
  {
    parent::TexyTextualElement($texy);
    $this->parentModule = & $texy->modules['TexyLinkModule'];

    $this->link = & $texy->createURL();
    $this->link->root = $this->parentModule->root;
  }



  function setLink($refName)
  {
    $elRef = & $this->parentModule->getReference( substr($refName, 1, -1) );
    if (!$elRef) return false;

    $this->refName = $refName;
    $this->modifier->copyFrom($elRef->modifier);

    $this->parentModule->_disableRefs = true;
    $this->parse($elRef->label);
    $this->parentModule->_disableRefs = false;

    if (@$elRef->URL{0} == '[' && @$elRef->URL{1} == '*') {
      $elImage = &new TexyImageElement($this->texy);
      $elImage->setImagesRaw(substr($elRef->URL, 2, -2));
      $elImage->requireLinkImage();
      $this->link->copyFrom($elImage->linkImage);
      return;
    }

    $this->link->set($elRef->URL);
  }





  function generateTag(&$tag, &$attr)
  {
    if (!$this->link->URL) return;  // image URL is required

    $tag  = 'a';

    $this->texy->summary->links[] = $attr['href'] = $this->link->URL;

    // rel="nofollow"
    $nofollowClass = in_array('nofollow', $this->modifier->unfilteredClasses);
    if (($this->link->type & TEXY_URL_ABSOLUTE) && ($nofollowClass || $this->nofollow || $this->parentModule->forceNoFollow))
      $attr['rel'] = 'nofollow';

    $attr['id']    = $this->modifier->id;
    $attr['title'] = $this->modifier->title;
    $classes = $this->modifier->classes;
    if ($nofollowClass) {
      $nofollowClass = array_search('nofollow', $classes);
      if ($nofollowClass !== false) unset($classes[$nofollowClass]);
    }
    $attr['class'] = TexyModifier::implodeClasses($classes);

    $styles = $this->modifier->styles;
    $attr['style'] = TexyModifier::implodeStyles($styles);

    // email on click
    if ($this->link->type & TEXY_URL_EMAIL)
      $attr['onclick'] = $this->parentModule->emailOnClick;

    // image on click
    if ($this->link->type & TEXY_URL_IMAGE_LINKED)
      $attr['onclick'] = $this->parentModule->imageOnClick;
  }





  function generateContent()
  {
    if ($this->content)
      return parent::generateContent();
    else
      return Texy::htmlChars($this->link->toString());
  }

} // TexyLinkRefElement





?>