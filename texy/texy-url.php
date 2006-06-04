<?php

/**
 * ------------------------
 *   TEXY! URL PROCESSING
 * ------------------------
 *
 * Version 1 Release Candidate
 *
 * Copyright (c) 2004-2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * See documentation on website http://www.texy.info/
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
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * TEXY! STRUCTURE FOR STORING URL
 * -------------------------------
 *
 * Analyse input text, detect type of url ($type)
 * and convert to final URL ($this->URL)
 *
 */
class TexyURL {
  var $URL;
  var $type;
  var $text;
  var $root = '';    // root of relative link


  function TexyURL() {
  }


  function set($text, $type = null) {
    if ($type !== null)
      $this->type = $type;

    $this->text = trim($text);
    $this->analyse();
    $this->toURL();
  }


  function clear() {
    $this->text = '';
    $this->type = 0;
    $this->URL = '';
  }


  function copyFrom(&$obj) {
    $this->text = $obj->text;
    $this->type = $obj->type;
    $this->URL  = $obj->URL;
    $this->root = $obj->root;
  }


  function analyse() {
    if (preg_match('#^'.TEXY_PATTERN_EMAIL.'$#i', $this->text)) $this->type |= TEXY_URL_EMAIL;
    elseif (preg_match('#(https?://|ftp://|www\.|ftp\.|/)#Ai', $this->text)) $this->type |= TEXY_URL_ABSOLUTE;
    else $this->type |= TEXY_URL_RELATIVE;
  }


  function toURL() {
    if (!$this->text)
      return $this->URL = '';

    if ($this->type & TEXY_URL_EMAIL) {
      $this->URL = 'mai';
      $s = 'lto:'.$this->text;
      for ($i=0; $i<strlen($s); $i++)  $this->URL .= '&#' . ord($s{$i}) . ';';
      return $this->URL;
    }

    if ($this->type & TEXY_URL_ABSOLUTE) {
      $textX = strtolower($this->text);

      if (substr($textX, 0, 4) == 'www.') {
        return $this->URL = 'http://'.$this->text;
      } elseif (substr($textX, 0, 4) == 'ftp.') {
        return $this->URL = 'ftp://'.$this->text;
      }
      return $this->URL = $this->text;
    }

    if ($this->type & TEXY_URL_RELATIVE) {
      return $this->URL = $this->root . $this->text;
    }
  }



  function toString() {
    if ($this->type & TEXY_URL_EMAIL) {
      return strtr($this->text, array('@' => '&nbsp;(at)&nbsp;'));
    }

    if ($this->type & TEXY_URL_ABSOLUTE) {
      $url = $this->text;
      $urlX = strtolower($url);
      if (substr($url, 0, 4) == 'www.') $url = '?://'.$url;
      if (substr($url, 0, 4) == 'ftp.') $url = '?://'.$url;

      $parts = parse_url($url);
      $res = '';
      if (isset($parts['scheme']) && $parts['scheme']{0} != '?')
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

    return $this->text;
  }


} // TexyURL






?>