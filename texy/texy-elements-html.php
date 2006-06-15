<?php

/**
 * ---------------------------------
 *   TEXY! DEFAULT "HTML" ELEMENTS
 * ---------------------------------
 *
 * Version 0.9 beta
 *
 * Copyright (c) 2003-2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * Elements of Texy! "DOM"
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
 * ELEMENT WHICH REPRESENTS HTML ELEMENT
 * -------------------------------------
 */
class TexyHTMLElement extends TexyDOMElement {
  var $tag;
  var $empty;
  var $block;
  var $attr;
  var $modifier;
  

  function __constructor(&$texy) {
    parent::__constructor($texy);
    $this->modifier = & new TexyModifier($texy);    
  }


 
  // build string with tag + attributes
  // from: tag & array('key' => 'abc"def',
  //   to: <tag key="abc&quot;def" />
  // (static method)
  function openingTag($tag, &$attr, $empty = false) {
    $attrStr = '';
    foreach ($attr as $name => $value) {
      $value = trim($value);
      if ($value == '') continue;
      $attrStr .= ' ' 
                  . Texy::htmlChars($name) 
                  . '="' 
                  . TexyFreezer::freezeSpaces(Texy::htmlChars($value))   // freezed spaces will be preserved during reformating
                  . '"';
    }
    return '<' . $tag . $attrStr . ($empty && $this->texy->xhtml ? ' /' : '') . '>';
  }



  function closingTag($tag) {
    return '</'.$tag.'>';
  }



  function prepareElement(&$tag, &$attr) {
    $tag  = $this->tag;
    $attr = array_merge((array) $this->attr, $this->modifier->toAttributes());
  }
  


  function toHTML() {
    $this->prepareElement($tag, $attr);
    $nl = $this->block ? TEXY_NEWLINE : '';   //  \n is necessary for "inline" post-processing
    
    if (!$tag) 
      return $nl.$this->childrenToHTML().$nl;

    if ($this->empty)              // empty elements
      return $nl.TexyHTMLElement::openingTag($tag, $attr, true).$nl; // freezing protect tags against post-processing

    else                          // elements with content
    
      return $nl.TexyHTMLElement::openingTag($tag, $attr).$nl       // freezing protect tags against post-processing
           . $this->childrenToHTML()
           . $nl.TexyHTMLElement::closingTag($tag).$nl;
  }


 
} // TexyHTMLElement








/**
 * HTML BLOCK ELEMENT (DIV / PARAGRAPH)
 * ------------------------------------
 */
class TexyGenericBlockElement extends TexyHTMLElement {
  var $block = true;
  
} // TexyGenericBlockElement





/**
 * HTML ELEMENT H1-6 
 * -----------------
 */
class TexyHeaderElement extends TexyHTMLElement {
  var $block = true;
  var $level;
  var $deltaLevel;
  var $topHeading;


  function __constructor(&$texy, $level = null) {
    parent::__constructor($texy);
    $this->level = $level;
    $this->topHeading = $texy->topHeading;
  }


  function prepareElement(&$tag, &$attr) {
    parent::prepareElement($tag, $attr);
    $tag = 'h' . min(6, max(0, $this->level + $this->deltaLevel + $this->topHeading));
  }

} // TexyHeaderElement







/**
 * HTML ELEMENT OL / UL 
 * --------------------
 */
class TexyListElement extends TexyHTMLElement {
  var $block = true;
  var $type;


  function prepareElement(&$tag, &$attr) {
    parent::prepareElement($tag, $attr);

    $tags = array(TEXY_LIST_UNORDERED => 'ul', TEXY_LIST_ORDERED => 'ol', TEXY_LIST_DEFINITION => 'dl');
    $tag = $tags[$this->type & 3]; // NOT GOOD!

    $styles = array(TEXY_LISTSTYLE_UPPER_ALPHA => 'list-style-type:upper-alpha;', TEXY_LISTSTYLE_LOWER_ALPHA => 'list-style-type:lower-alpha;', TEXY_LISTSTYLE_UPPER_ROMAN => 'list-style-type:upper-roman;');
    @$attr['style'] .= $styles[$this->type & (3<<2)]; // ABSOLUTLY NOT GOOD!
  }

} // TexyListElement





/**
 * HTML ELEMENT LI / DL / DT
 * -------------------------
 */
class TexyListItemElement extends TexyHTMLElement {
  var $block = true;
  var $type;


  function prepareElement(&$tag, &$attr) {
    parent::prepareElement($tag, $attr);

    $tags = array(TEXY_LISTITEM => 'li', TEXY_LISTITEM_TERM => 'dt', TEXY_LISTITEM_DEFINITION => 'dd');
    $tag = $tags[$this->type & 3]; // NOT GOOD!
  }
} // TexyListItemElement




/**
 * HTML ELEMENT BLOCKQUOTE
 * -----------------------
 */
class TexyQuoteElement extends TexyHTMLElement {
  var $cite;


  function prepareElement(&$tag, &$attr) {
    parent::prepareElement($tag, $attr);
    $tag = $this->block ? 'blockquote' : 'q';
    if ($this->cite->URL) $attr['cite'] = $this->cite->URL;
  }

} // TexyBlockQuoteElement






/**
 * HTML ELEMENT TABLE 
 * ------------------
 */
class TexyTableElement extends TexyHTMLElement {
  var $tag = 'table';
  var $block = true;
  
} // TexyTableElement






/**
 * HTML ELEMENT TR
 * ---------------
 */
class TexyTableRowElement extends TexyHTMLElement {
  var $tag = 'tr';
  var $block = true;
  var $isHead;

} // TexyTableRowElement






/**
 * HTML ELEMENT TD / TH
 * --------------------
 */
class TexyTableFieldElement extends TexyHTMLElement {
  var $block = true;
  var $colSpan = 1;
  var $rowSpan = 1;
  var $isHead;
   
  function prepareElement(&$tag, &$attr) {
    parent::prepareElement($tag, $attr);
    $tag = $this->isHead ? 'th' : 'td';
    if ($this->colSpan <> 1) $attr['colspan'] = (int) $this->colSpan;
    if ($this->rowSpan <> 1) $attr['rowspan'] = (int) $this->rowSpan;
  }

} // TexyTableFieldElement





/**
 * HTML ELEMENT HORIZONTAL LINE
 * ----------------------------
 */
class TexyHorizLineElement extends TexyHTMLElement {
  var $tag = 'hr';
  var $block = true;
  var $empty = true;

} // TexyHorizLineElement




/**
 * HTML ELEMENT LINE BREAK
 * -----------------------
 */
class TexyLineBreakElement extends TexyHTMLElement {
  var $tag = 'br';
  var $block = true;
  var $empty = true;

} // TexyLineBreakElement







/**
 * HTML ELEMENT PRE + CODE
 * -----------------------
 */
class TexyCodeElement extends TexyHTMLElement {
  var $tag = 'code';
  var $content;


  function toHTML() {  
    $this->prepareElement($tag, $attr);
    return (
             ($this->block ? '<pre>' : '')
             . TexyHTMLElement::openingTag($this->tag, $attr)
             . $this->content
             . TexyHTMLElement::closingTag($this->tag)
             . ($this->block ? '</pre>'.TEXY_NEWLINE : '')
           );
  }


  function hasTextualContent() {
    return true;
  }
  
} // TexyCodeElement





/**
 * HTML ACRONYM ELEMENT
 * --------------------
 */
class TexyAcronymElement extends TexyHTMLElement {
  var $tag = 'acronym';
  var $description;


  function prepareElement(&$tag, &$attr) {
    parent::prepareElement($tag, $attr);
    $attr['title'] = $this->description;
  }
  
} // TexyAcronymElement








/**
 * HTML ELEMENT ANCHOR
 * -------------------
 */
class TexyLinkElement extends TexyHTMLElement {
  var $tag = 'a';
  var $link; 


  function __constructor(&$texy) {
    parent::__constructor($texy);   
    $this->link = & $this->texy->createURL();
  }


  function setLink($link) {
    if (preg_match('#'.TEXY_PATTERN_LINK_REF.'$#Ai', $link)) {
      return $this->fromReference(substr($link, 1, -1));
    }

    if (preg_match('#'.TEXY_PATTERN_LINK_IMAGE.'$#Ai', $link)) {
      $name = substr($link, 2, -2);
      $elImage = &$this->texy->getReference($name, true);
      if ($elImage) {
        $elImage->requireLinkImage();
        $this->link->copyFrom($elImage->linkImage);
      } else {
        $this->link->set($name, TEXY_IMAGE_LINK);
      }
      return true;
    }
    
    if (preg_match('#("|\')(.*)\\1$#A', $link, $matches)) $link = $matches[2];
    $this->link->set($link);
    return true;
  }



  function prepareElement(&$tag, &$attr) {
    parent::prepareElement($tag, $attr);
    if ($this->link->URL && $this->children) {
      $attr['href'] = $this->link->URL;
      if ($this->link->type & TEXY_IMAGE_LINK)
        $attr['onclick'] = $this->texy->imageOnClick;
    } else $tag = '';
  }



  function requireContent() {
    if (count($this->children)) return;
    
    $type = $this->link->type;
    if ($type & TEXY_IMAGE_LINK) {
      $this->children = array( $this->link->text );
      return;
    }

    if ($type & TEXY_LINK_EMAIL) {
      $this->children = array( strtr($this->link->text, array('@' => '&nbsp;(@)&nbsp;')) );
      return; 
    }
      
    if ($type & TEXY_LINK_ABSOLUTE) {
      $url = $this->link->text;
      if (preg_match('#www\.#Ai', $url)) $url = 'http://'.$url;
      if (preg_match('#ftp\.#Ai', $url)) $url = 'ftp://'.$url;

      $parts = parse_url($url);
      $res = '';
      if (isset($parts['scheme']))
        $res .= $parts['scheme'] . '://';

      if (isset($parts['host'])) 
        $res .= $parts['host'];

      if (isset($parts['path'])) 
        $res .=  (strlen($parts['path']) > 16 ? ('/...' . preg_replace('#^.*(.{0,12})$#U', '$1', $parts['path'])) : $parts['path']);
      else
        $res .= '/';

      if (isset($parts['query'])) {
        $res .= strlen($parts['query']) > 4 ? '?...' : ('?'.$parts['query']);
      } elseif (isset($parts['fragment'])) {
        $res .= strlen($parts['fragment']) > 4 ? '#...' : ('#'.$parts['fragment']);
      }
      $this->children = array( $res );
      return;
    }

    if ($type & TEXY_LINK_RELATIVE) {
        $this->children = array( $this->link->URL );
      return;
    }
  }


  function fromReference($name) {
    $elLink = &$this->texy->getReference($name);
    if (!$elLink) return false;
    
    $this->link->copyFrom($elLink->link);
    $this->modifier->copyFrom($elLink->modifier);
    return true;
  }


} // TexyLinkElement














/**
 * HTML ELEMENT IMAGE
 * ------------------
 */
class TexyImageElement extends TexyHTMLElement {
  var $tag = 'img';
  var $empty = true;
  var $image;
  var $overImage;
  var $linkImage;


  function __constructor(&$texy) {
    parent::__constructor($texy);   
    $this->image = & $this->texy->createURL();
    $this->overImage = & $this->texy->createURL();
    $this->linkImage = & $this->texy->createURL();
  }


  function setImages($URLs) {
    if ($this->fromReference(trim($URLs))) return true;

    $URLs = explode('|', $URLs);
    
    if (isset($URLs[0])) 
      $this->image->set($URLs[0], TEXY_IMAGE);
    else
      $this->image->clear();

    if (isset($URLs[1])) 
      $this->overImage->set($URLs[1], TEXY_IMAGE);
    else
      $this->overImage->clear();

    if (isset($URLs[2])) 
      $this->linkImage->set($URLs[2], TEXY_IMAGE_LINK);
    else
      $this->linkImage->clear();

    return true;
  }




  function prepareElement(&$tag, &$attr) {
    if (!$this->image->URL) {
      $tag = '';
      return;
    }

    $modifier = & $this->modifier;
    if ($modifier->hAlign == TEXY_HALIGN_LEFT) {
      if ($this->texy->imageLeftClass) 
        $modifier->classes[] = $this->texy->imageLeftClass;
      else 
        $modifier->styles['float'] = 'left';
      
    } elseif ($modifier->hAlign == TEXY_HALIGN_RIGHT)  {

      if ($this->texy->imageRightClass) 
        $modifier->classes[] = $this->texy->imageRightClass;
      else 
        $modifier->styles['float'] = 'right';
    }
    unset($modifier->styles['text-align']);

    parent::prepareElement($tag, $attr);

    $attr['src'] = $this->image->URL;
    if ($this->overImage->URL) {
      $attr['onmouseover'] = 'this.src=\''.$this->overImage->URL.'\'';
      $attr['onmouseout'] = 'this.src=\''.$this->image->URL.'\'';
    }

    $attr['alt'] = $attr['title'] ? $attr['title']  : $this->texy->imageAlt;
    unset($attr['title']);
  }




  function requireLinkImage() {
    if (!$this->linkImage->URL)
      $this->linkImage->set($this->image->text, TEXY_IMAGE_LINK);
  }




  function fromReference($name) {
    $elImage = &$this->texy->getReference($name, true);
    if (!$elImage) return false;
    
    $this->image->copyFrom($elImage->image);
    $this->overImage->copyFrom($elImage->overImage);
    $this->linkImage->copyFrom($elImage->linkImage);
    $this->modifier->copyFrom($elImage->modifier);
    return true;
  }


} // TexyImages






/**
 * HTML ELEMENT IMAGE (WITH DESCRIPTION)
 * -------------------------------------
 */
class TexyImageDescElement extends TexyGenericBlockElement {
  var $tag   = 'div';
  var $class = 'description';

  var $image;
  var $description;


  function __constructor(&$texy) {
    parent::__constructor($texy);
    $this->modifier->classes[] = $this->class;
    $this->image = & new TexyImageElement($this->texy);
    $this->description = array();

    $desc = & new TexyGenericBlockElement($this->texy);
    $desc->tag = 'p';
    $desc->children = & $this->description;
    $this->children = array( &$this->image, &$desc);
  }


  

}  // TexyImageDescElement

?>