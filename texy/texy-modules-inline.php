<?php

/**
 * --------------------------------
 *   TEXY! DEFAULT INLINE MODULES
 * --------------------------------
 *
 * Version 0.9 beta
 *
 * Copyright (c) 2003-2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * Modules for parsing parts of text 
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
 * LINKS inline module
 * ----------------------------
 *
 *    Do you know "La Trine":[la trine]?
 *
 *    [la trine]: http://www.dgx.cz/trine/ anchor text .(title)
 */
class TexyLinkModule extends TexyModule {
  

  // pre-process

  function preProcess() {
    // [la trine]: http://www.dgx.cz/trine/ text odkazu .(title)[class]{style}
    $this->texy->text = preg_replace_callback('#^('.TEXY_PATTERN_LINK_REF.'): +('.TEXY_PATTERN_LINK_IMAGE.'|(?-U)(?!\[)\S+(?U))(\ .+)?\ *'.TEXY_PATTERN_MODIFIER.'?()$#mU', array(&$this, 'reference'), $this->texy->text);
  }



  function reference(&$matches) {
    list($match, $mRef, $mLink, $mLabel, $mMod1, $mMod2, $mMod3) = $matches;
    //    [1] => [reference]
    //    [2] => link
    //    [3] => ...
    //    [4] => (title)
    //    [5] => [class]
    //    [6] => {style}

    if ($mLink{0} == '"' || $mLink{0} == '\'') $mLink = substr($mLink, 1, -1);

    $elLink = &new TexyLinkElement($this->texy);
    $elLink->setLink($mLink);
    $elLink->modifier->setProps($mMod1, $mMod2, $mMod3);
    if ($mLabel) 
      $elLink->children = $this->texy->parseInline(trim($mLabel));
    else
      $elLink->requireContent();

    $this->texy->addReference(substr($mRef, 1, -1), $elLink);

    return '';
  }



  // inline process

  function init() {
    // "... .(title)[class]{style}":LINK    where LINK is:   url | [ref] | [*image*]
    $this->registerInlinePattern('replaceQuot',      '#(?<!\")\"(?!\ )([^\n\"]+)MODIFIER?(?<!\ )\"'.TEXY_PATTERN_LINK.'()#U');
    $this->registerInlinePattern('replaceQuot',      '#(?<!\')\'(?!\ )([^\n\']+)MODIFIER?(?<!\ )\''.TEXY_PATTERN_LINK.'()#U');

    // [ref]
    $this->registerInlinePattern('replaceReference', '#('.TEXY_PATTERN_LINK_REF.')#U');

    $this->registerInlinePattern('replaceURL',       '#(?<=\s|^|\(|\[|:)(?:https?://|www\.|ftp://|ftp\.)[a-z0-9.-][/a-z\d+\.~%&?@=_:;\#,-]+[/\w\d+~%?@=_\#]#i'.TEXY_PATTERN_UTF);
    $this->registerInlinePattern('replaceURL',       '#(?<=\s|^|\(|\[|:)'.TEXY_PATTERN_EMAIL.'#i');
  }




  function replaceQuot(&$matches) {
    list($match, $mContent, $mMod1, $mMod2, $mMod3, $mLink) = $matches;
    //    [1] => ...
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => url | [ref] | [*image*]

    $elLink = &new TexyLinkElement($this->texy);
    $elLink->setLink($mLink);
    $elLink->modifier->setProps($mMod1, $mMod2, $mMod3);
    $elLink->children = & $this->texy->parseInline($mContent);
    return array(&$elLink);
  }




  function replaceReference(&$matches) {
    list($match, $mRef) = $matches;
    //    [1] => [ref]

    $elLink = & $this->texy->getReference( substr($mRef, 1, -1) );
    if (!$elLink) return array($match);
    return array(&$elLink);
  }



  function replaceURL(&$matches) {
    list($mURL) = $matches;
    //    [0] => URL

    $elLink = &new TexyLinkElement($this->texy);
    $elLink->setLink($mURL);
    $elLink->children = array($elLink->requireContent());
    return array(&$elLink);
  }

} // TexyLinkModule















/**
 * IMAGES inline module
 * --------------------
 *
 *    This is [* image.gif *]:link
 *
 *    [* texy.gif *]: small.jpg | small-over.jpg | big.jpg .(alternative text)[class]{style}>
 */
class TexyImageModule extends TexyModule {
  

  // pre-process

  function preProcess() {
    // [*image*]: urls .(title)[class]{style}
    $this->texy->text = preg_replace_callback('#^('.TEXY_PATTERN_LINK_IMAGE.'):\ +(.+)\ *'.TEXY_PATTERN_MODIFIER.'?()$#mU', array(&$this, 'reference'), $this->texy->text);
  }



  function reference(&$matches) {
    list($match, $mRef, $mUrls, $mMod1, $mMod2, $mMod3) = $matches;
    //    [1] => [*reference*]
    //    [2] => urls
    //    [3] => (title)
    //    [4] => [class]
    //    [5] => {style}

    $elImage = &new TexyImageElement($this->texy);
    $elImage->setImages($mUrls);
    $elImage->modifier->setProps($mMod1, $mMod2, $mMod3);

    $this->texy->addReference(substr($mRef, 2, -2), $elImage, true);

    return '';
  }



  // inline & block process

  function init() {
    // [*image*]:LINK    where LINK is:   url | [ref] | [*image*]
    $this->registerInlinePattern('replace',     '#'.TEXY_PATTERN_IMAGE.'(?-U)(?::(?U)('.TEXY_PATTERN_LINK_REF.'|'.TEXY_PATTERN_LINK_IMAGE.'|'.TEXY_PATTERN_LINK_URL.'|:))?()#U');
    $this->registerBlockPattern('blockProcess', '#^'.TEXY_PATTERN_IMAGE.'(?::('.TEXY_PATTERN_LINK_REF.'|'.TEXY_PATTERN_LINK_IMAGE.'|'.TEXY_PATTERN_LINK_URL.'|:)) +\*\*\* +(.*)MODIFIER_H?()$#mU');
  }



  function blockProcess(&$block, &$matches) {
    list($match, $mURLs, $mImgMod1, $mImgMod2, $mImgMod3, $mImgMod4, $mLink, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
    //    [1] => URLs
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => >
    //    [6] => url | [ref] | [*image*]
    //    [7] => ...
    //    [8] => (title)
    //    [9] => [class]
    //    [10] => {style}
    //    [11] => >

    $el = &new TexyImageDescElement($this->texy);
    $el->image->setImages($mURLs);
    $el->image->modifier->setProps($mImgMod1, $mImgMod2, $mImgMod3, $mImgMod4);
    $el->modifier->setProps($mMod1, $mMod2, $mMod3, $mMod4);
    $el->description = $this->texy->parseInline(ltrim($mContent));

    $block->children[] = & $el;
  }




  function replace(&$matches) {
    list($match, $mURLs, $mMod1, $mMod2, $mMod3, $mMod4, $mLink) = $matches;
    //    [1] => URLs
    //    [2] => (title)
    //    [3] => [class]
    //    [4] => {style}
    //    [5] => >
    //    [6] => url | [ref] | [*image*]

    $elImage = &new TexyImageElement($this->texy);
    $elImage->setImages($mURLs);
    $elImage->modifier->setProps($mMod1, $mMod2, $mMod3, $mMod4);

    if ($mLink) {
      $elLink = &new TexyLinkElement($this->texy);
      if ($mLink == ':') {
        $elImage->requireLinkImage();
        $elLink->link->copyFrom($elImage->linkImage);
      } else
        $elLink->setLink($mLink);
      
      $elLink->children = array(&$elImage);
      return array(&$elLink);
    }

    return array(&$elImage);
  }


} // TexyImageModule










/**
 * HTML TAGS inline module
 * --------------------------------------
 *
 *    <html ...>
 *
 */
class TexyHTMLTagModule extends TexyModule {
  var $userElements;        // user function f(tag, attr, content): array


  function __constructor(&$texy) {
    $this->texy = & $texy;
    $this->userElements = array(
      'a'      => array(&$this, 'aElement'),
      'b'      => array(&$this, 'defaultElement'),
      'i'      => array(&$this, 'defaultElement'),
      'strong' => array(&$this, 'defaultElement'),
      'em'     => array(&$this, 'defaultElement'),
    );
  }


  function init() {
    $this->registerInlinePattern('replace', '#<'.TEXY_PATTERN_INLINE_TAG.'(|\s(?:[\sa-z0-9-]|=\s*"[^"]*"|=\s*\'[^\']*\'|=[^>]*)*)>(.*)</\\1>#is', false);
    $this->registerInlinePattern('replace', '#<'.TEXY_PATTERN_EMPTY_TAG.'(|\s(?:[\sa-z0-9-]|=\s*"[^"]*"|=\s*\'[^\']*\'|=[^>]*)*)/?>#Uis', true);
  }


  function buildAttr($s) {
    $attr = array();                   
    preg_match_all('#([a-z0-9-]+)\s*(?:=\s*(\'[^\']*\'|"[^"]*"|[^\'"\s]+))?#is', $s, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      $key = strtolower($match[1]);
      $value = $match[2];
      if (!$value) $value = $key;
      elseif ($value{0} == '\'' || $value{0} == '"') $value = substr($value, 1, -1);
      $attr[$key] = $value;
    }
    return $attr;
  }



  function replace(&$matches, $empty) {
    list($match, $mTag, $mAttr, $mContent) = $matches;
    //    [1] => tag
    //    [2] => attributes
    //    [3] => ....

    $mTag = strtolower($mTag);

    if (isset($this->userElements[$mTag])) 
      return call_user_func_array(
                   $this->userElements[$mTag], 
                   array($mTag, 
                     $this->buildAttr($mAttr),
                     $empty ? false : $mContent
                   )
             );
  }



  function aElement($tag, $attr = null, $content = null) {
    if (!@$attr['href']) return array();
    
    $el = &new TexyLinkElement($this->texy);
    $el->setLink($attr['href']);
    $el->children = $this->texy->parseInline($content);
    return array(&$el);
  }


  function defaultElement($tag, $attr = null, $content = null) {
    $el = &new TexyHTMLElement($this->texy);
    $el->tag = $tag;
//    if ($attr) $el->attr = $attr; 
    $el->empty = $content === false;
    if (!$el->empty) 
      $el->children = $this->texy->parseInline($content);
    return array(&$el);
  }


} // TexyHTMLTagModule












/**
 * PHRASES inline module
 * ---------------------
 *
 *   **strong**
 *   *emphasis*
 *   ??quote??:link
 *   ^superscript^
 *   _subscript_
 *   ++inserted++
 *   --deleted--
 *   "..."
 */
class TexyPhrasesModule extends TexyModule {

  function init() {
    $this->registerInlinePattern('replace', '#(?<!\?)(\?\?)(?!\ |\?)(.+)MODIFIER?(?<!\ |\?)\\1(?!\?)LINK?()#U', 'q');
    $this->registerInlinePattern('replace', '#(?<!\+)(\+\+)(?!\ |\+)(.+)MODIFIER?(?<!\ |\+)\\1(?!\+)()#U', 'ins');
    $this->registerInlinePattern('replace', '#(?<!\+)(\-\-)(?!\ |\-)(.+)MODIFIER?(?<!\ |\-)\\1(?!\-)()#U', 'del');
    $this->registerInlinePattern('replace', '#(?<!\^)(\^)(?!\ |\^)(.+)MODIFIER?(?<!\ |\^)\\1(?!\^)()#U', 'sup');
    $this->registerInlinePattern('replace', '#(?<!\_)(\_)(?!\ |\_)(.+)MODIFIER?(?<!\ |\_)\\1(?!\_)()#U', 'sub');
    $this->registerInlinePattern('replace', '#(?<!\")(\")(?!\ |\")(.+)MODIFIER(?<!\ |\")\\1(?!\")()#U', 'span');
    $this->registerInlinePattern('replace', '#(?<!\')(\')(?!\ |\')(.+)MODIFIER(?<!\ |\')\\1(?!\')()#U', 'span');
    $this->registerInlinePattern('replace', '#(?<!\*)(\*\*\*)(?!\ |\*)(.+)MODIFIER?(?<!\ |\*)\\1(?!\*)()#U', 'strongem');
    $this->registerInlinePattern('replace', '#(?<!\*)(\*\*)(?!\ |\*)(.+)MODIFIER?(?<!\ |\*)\\1(?!\*)()#U', 'strong');
    $this->registerInlinePattern('replace', '#(?<!\*)(\*)(?!\ |\*)(.+)MODIFIER?(?<!\ |\*)\\1(?!\*)()#U', 'em');
  }


  function replace(&$matches, $tag) {
    list($match, $mMark, $mContent, $mMod1, $mMod2, $mMod3, $mLink) = $matches;
    //    [1] => **
    //    [2] => ...
    //    [3] => (title)
    //    [4] => [class]
    //    [5] => {style}
    //    [6] => LINK

    if ($tag == 'q') {
      $el = &new TexyQuoteElement($this->texy);
      $el->block = false;
      $el->children = & $this->texy->parseInline($mContent);
      if ($mLink) {
        $el->cite = & $this->texy->createURL();
        $el->cite->set($mLink);
      }

    } elseif ($tag == 'strongem') {
      $el = &new TexyHTMLElement($this->texy);
      $el->tag = 'strong';
      $el->block = false;
      $el->modifier->setProps($mMod1, $mMod2, $mMod3);
      $el->children[] = &new TexyHTMLElement($this->texy);
      $el->children[0]->tag = 'em';
      $el->children[0]->block = false;
      $el->children[0]->children = & $this->texy->parseInline($mContent);

    } else {
      $el = &new TexyHTMLElement($this->texy);
      $el->tag = $tag;
      $el->modifier->setProps($mMod1, $mMod2, $mMod3);
      $el->children = & $this->texy->parseInline($mContent);
    }
    

    return array(&$el);
  }

} // TexyPhrasesModule






/**
 * ACRONYM inline module
 * ---------------------
 *
 * ÈEDOK(Cestovní dopravní kanceláø)
 *
 */
class TexyAcronymModule extends TexyModule {
  var $TexyAcronymElement = 'TexyAcronymElement';

  function init() {
    $this->registerInlinePattern('replace',  '#\b([A-Z][A-Z0-9]{2,})\b(?:[(]([^)]*)[)])#');
  }

  function replace(&$matches) {
    list($match, $mAcronym, $mExplain) = $matches;
    //    [1] => ÈEDOK
    //    [2] => Cestovní a dopravní kanceláø

    $el = &new TexyAcronymElement($this->texy);
    $el->description = $mExplain;
    $el->children = & $this->texy->parseInline($mAcronym);
    return array(&$el);
  }

} // TexyAcronymModule














/**
 * SCRIPTS
 * -------
 *
 *    ${...}
 *
 */
class TexyScriptModule extends TexyModule {


  function init() {
    $this->registerInlinePattern('replace', '#\$\{([^\}])+\}()#U');
  }


  function replace(&$matches, $tag) {
    list($match, $mContent) = $matches;
    //    [1] => ...

    $el = &new TexyScriptElement($this->texy);
    return array(&$el);
  }


} // TexyScriptModule



?>