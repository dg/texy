<?php

/**
 * --------------------
 *   TEXY! CACHE DEMO
 * --------------------
 *
 * Copyright (c) 2004-2005, David Grudl <dave@dgx.cz>. All rights reserved.
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


$libs_path = '../../texy/';
$texy_path = $libs_path;


// configuration
define ('TEXY_UTF8', false);  // disable UTF-8

// include libs
require_once($texy_path . 'texy.php');


// function 'file_put_contents' is missing in PHP 4
if (!function_exists('file_put_contents')) {
  function file_put_contents($fileName, $s) {
    $f = fopen($fileName, 'w');
    fwrite($f, $s);
    fclose($f);
  }
}







// MY OWN TEXY! OBJECT

class MyTexy extends Texy {
  var $cachePath = './cache/';
  var $time;


  function MyTexy() {
    parent::Texy();

    // some configurations
    $this->images->leftClass   = 'left';
    $this->images->rightClass  = 'right';
  }




  function process($text, $useCache = true) {
    $this->time = -$this->getTime();
    if ($useCache) {
      $md5 = md5($text); // md5 is key for caching

      // check, if cached file exists
      $cacheFile = $this->cachePath . $md5 . '.html';
      if (is_file($cacheFile)) {         // read from cache
        list($html, $this->styleSheet, $this->headings->title) = unserialize(file_get_contents($cacheFile));

      } else {                           // doesn't exists
        $html = parent::process($text);
        file_put_contents($cacheFile,
          serialize( array($html, $this->styleSheet, $this->headings->title) )
        );
      }

    } else { // if caching is disabled
      $html = parent::process($text);
    }

    $this->time += $this->getTime();
    return $html;
  }





  function getTime() {
    list($usec, $sec) = explode(' ',microtime());
    return ((float) $usec + (float) $sec);
  }

}



?>