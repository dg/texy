<?php

/**
 * TEXY! CACHE DEMO
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://www.texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 */





// include libs
require_once dirname(__FILE__).'/../../texy/texy.php';



// function 'file_put_contents' is missing in PHP 4
if (!function_exists('file_put_contents')) {
  function file_put_contents($fileName, $s)
  {
    $f = fopen($fileName, 'w');
    fwrite($f, $s);
    fclose($f);
  }
}






// MY OWN TEXY! OBJECT

class MyTexy extends Texy {
  var $cachePath = './cache/';
  var $time;
  var $utf = FALSE;


  function __construct()
  {
    parent::__construct();

    // some configurations
    $this->imageModule->leftClass   = 'left';
    $this->imageModule->rightClass  = 'right';
  }




  function process($text, $useCache = TRUE)
  {
    $this->time = -$this->getTime();
    if ($useCache) {
      $md5 = md5($text); // md5 is key for caching

      // check, if cached file exists
      $cacheFile = $this->cachePath . $md5 . '.html';
      $content = is_file($cacheFile) ? unserialize(file_get_contents($cacheFile)) : NULL;
      if ($content) {         // read from cache
        list($html, $this->styleSheet, $this->headingModule->title) = $content;

      } else {                           // doesn't exists
        $html = parent::process($text);
        file_put_contents($cacheFile,
          serialize( array($html, $this->styleSheet, $this->headingModule->title) )
        );
      }

    } else { // if caching is disabled
      $html = parent::process($text);
    }

    $this->time += $this->getTime();
    return $html;
  }





  function getTime()
  {
    list($usec, $sec) = explode(' ',microtime());
    return ((float) $usec + (float) $sec);
  }

}



?>