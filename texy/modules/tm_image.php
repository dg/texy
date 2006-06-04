<?php

/**
 * ---------------------------------
 *   IMAGES - TEXY! DEFAULT MODULE
 * ---------------------------------
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
 * IMAGES MODULE CLASS
 */
class TexyImageModule extends TexyModule {
  // options
  var $allowed    = true;          // generally disable / enable images
  var $root       = 'images/';     // root of relative images (http)
  var $linkedRoot = 'images/';     // root of linked images (http)
  var $rootPrefix = '';            // physical location on server
  var $leftClass  = '';            // left-floated image modifier
  var $rightClass = '';            // right-floated image modifier
  var $defaultAlt = 'image';       // default image alternative text

  // private
  var $references  = array();      // references: 'home' => TexyImageReference
  var $userReferences;             // function &myUserFunc(&$texy, $refName): returns TexyImageReference (or false)
  var $_backupReferences;




  /***
   * Module initialization.
   */
  function init() {
    // [*image*]:LINK    where LINK is:   url | [ref] | [*image*]
    $this->registerLinePattern('processLine',     '#'.TEXY_PATTERN_IMAGE.TEXY_PATTERN_LINK_N.'?()#U');

    Texy::adjustDir($this->root);
    Texy::adjustDir($this->linkedRoot);
    Texy::adjustDir($this->rootPrefix);
  }




  /***
   * Add new named image
   */
  function addReference($name, &$obj) {
    $name = strtolower($name);
    $this->references[$name] = &$obj;
  }




  /***
   * Receive new named link. If not exists, try
   * call user function to create one.
   */
  function &getReference($name) {
    $name = strtolower($name);

    if (isset($this->references[$name]))
      return $this->references[$name];


    if ($this->userReferences) {
      $obj = &call_user_func_array(
                   $this->userReferences,
                   array(&$this->texy, $name)
      );

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
  function forgetReferences() {
    $this->references = $this->_backupReferences;
  }



  /***
   * Preprocessing
   */
  function preProcess(&$text) {
    $this->_backupReferences = $this->references;

    // [*image*]: urls .(title)[class]{style}
    $text = preg_replace_callback('#^\[\*([^\n]+)\*\]:\ +(.+)\ *'.TEXY_PATTERN_MODIFIER.'?()$#mU', array(&$this, '_replaceReference'), $text);
  }



  /***
   * Callback function: [*image*]: urls .(title)[class]{style}
   * @return string
   */
  function _replaceReference(&$matches) {
    if (!$this->allowed) return '';
    list($match, $mRef, $mUrls, $mMod1, $mMod2, $mMod3) = $matches;
    //    [1] => [* (reference) *]
    //    [2] => urls
    //    [3] => (title)
    //    [4] => [class]
    //    [5] => {style}

    $elRef = &new TexyImageReference($this->texy, $mUrls);
    $elRef->modifier->setProperties($mMod1, $mMod2, $mMod3);

    $this->addReference($mRef, $elRef);

    return '';
  }






  /***
   * Callback function: [* texy.gif *]: small.jpg | small-over.jpg | big.jpg .(alternative text)[class]{style}>]:LINK
   * @return string
   */
  function processLine(&$lineParser, &$matches) {
    if (!$this->allowed) return '';
    list($match, $mURLs, $mMod1, $mMod2, $mMod3, $mMod4, $mLink) = $matches;
    //    [1] => URLs
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => >
    //    [6] => url | [ref] | [*image*]

    $elImage = &new TexyImageElement($this->texy);
    $elImage->setImagesRaw($mURLs);
    $elImage->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

    if ($mLink) {
      $elLink = &new TexyLinkElement($this->texy);
      if ($mLink == ':') {
        $elImage->requireLinkImage();
        $elLink->link->copyFrom($elImage->linkImage);
      } else {
        $elLink->setLinkRaw($mLink);
      }

      return $elLink->addTo(
                         $lineParser->element,
                         $elImage->addTo($lineParser->element)
                      );
    }

    return $elImage->addTo($lineParser->element);
  }





} // TexyImageModule






class TexyImageReference {
  var $URLs;
  var $modifier;


  // constructor
  function TexyImageReference(&$texy, $URLs = null) {
    $this->modifier = & $texy->createModifier();
    $this->URLs = $URLs;
  }

}






/****************************************************************************
                               TEXY! DOM ELEMENTS                          */






/**
 * HTML ELEMENT IMAGE
 */
class TexyImageElement extends TexyTextualElement {
  var $parentModule;
  var $tag = 'img';

  var $image;
  var $overImage;
  var $linkImage;


  // constructor
  function TexyImageElement(&$texy) {
    parent::TexyTextualElement($texy);
    $this->parentModule = & $texy->modules['TexyImageModule'];

    $this->image = & $texy->createURL();
    $this->image->root = $this->parentModule->root;

    $this->overImage = & $texy->createURL();
    $this->overImage->root = $this->parentModule->root;

    $this->linkImage = & $texy->createURL();
    $this->linkImage->root = $this->parentModule->linkedRoot;
  }



  function setImages($URL = null, $URL_over = null, $URL_link = null) {
    if ($URL)
      $this->image->set($URL, TEXY_URL_IMAGE_INLINE);
    else
      $this->image->clear();

    if ($URL_over)
      $this->overImage->set($URL_over, TEXY_URL_IMAGE_INLINE);
    else
      $this->overImage->clear();

    if ($URL_link)
      $this->linkImage->set($URL_link, TEXY_URL_IMAGE_LINKED);
    else
      $this->linkImage->clear();
  }


  function setSize($width, $height) {
    $width = abs((int) $width);
    $height = abs((int) $height);

    if ($width && $height) {
      $this->modifier->extra['width'] = $width;
      $this->modifier->extra['height'] = $height;
    } else {
      unset($this->modifier->extra['width']);
      unset($this->modifier->extra['height']);
    }
  }


  // private
  function setImagesRaw($URLs) {
    $elRef = &$this->parentModule->getReference(trim($URLs));
    if ($elRef) {
      $URLs = $elRef->URLs;
      $this->modifier->copyFrom($elRef->modifier);
    }

    $URLs = explode('|', $URLs . '||');

    // dimensions
    if (preg_match('#^(.*) (\d+) *x *(\d+) *()$#U', $URLs[0], $matches)) {
      $URLs[0] = $matches[1];
      $this->setSize($matches[2], $matches[3]);
    }

    $this->setImages($URLs[0], $URLs[1], $URLs[2]);
  }




  function generateTag(&$tag, &$attr) {
    if (!$this->image->URL) {  // image URL is required
      $tag = '';
      return;
    }

    // modifiers
    $modifier = & $this->modifier;
    if ($modifier->hAlign == TEXY_HALIGN_LEFT) {
      if ($this->parentModule->leftClass)
        $modifier->classes[] = $this->parentModule->leftClass;
      else
        $modifier->styles['float'] = 'left';

    } elseif ($modifier->hAlign == TEXY_HALIGN_RIGHT)  {

      if ($this->parentModule->rightClass)
        $modifier->classes[] = $this->parentModule->rightClass;
      else
        $modifier->styles['float'] = 'right';
    }
    unset($modifier->styles['text-align']);

    // width x height generate
    $this->requireSize();

    // tag generate
    parent::generateTag($tag, $attr);

    // attribute generate
    $this->texy->summary->images[] = $attr['src'] = $this->image->URL;

    // onmouseover actions generate
    if ($this->overImage->URL) {
      $attr['onmouseover'] = 'this.src=\''.$this->overImage->URL.'\'';
      $attr['onmouseout'] = 'this.src=\''.$this->image->URL.'\'';
      $this->texy->summary->preload[] = $this->overImage->URL;
    }

    // alternative text generate
    $attr['alt'] = $attr['title'] ? $attr['title']  : $this->parentModule->defaultAlt;
    unset($attr['title']);
  }



  function requireSize() {
    if (isset($this->modifier->extra['width'])) return;

    $file = $this->parentModule->rootPrefix . $this->image->URL;
    if (!is_file($file)) return false;

    $size = getImageSize($file);
    if (!is_array($size)) return false;

    $this->setSize($size[0], $size[1]);
  }


  function requireLinkImage() {
    if (!$this->linkImage->URL)
      $this->linkImage->set($this->image->text, TEXY_URL_IMAGE_LINKED);
  }



} // TexyImages







?>