<?php

/**
 * TEXY! CACHE DEMO
 * --------------------------------------
 *
 * @author   David Grudl aka -dgx- (http://www.dgx.cz)
 * @version  $Revision$ $Date$
 */





// include libs
require_once dirname(__FILE__).'/../../texy/texy.php';




// MY OWN TEXY! OBJECT

class MyTexy extends Texy
{
    public $cachePath = './cache/';
    public $time;


    function __construct()
    {
        parent::__construct();

        // some configurations
        $this->imageModule->leftClass   = 'left';
        $this->imageModule->rightClass  = 'right';
    }




    function process($text, $useCache = TRUE)
    {
        $this->time = -microtime(TRUE);

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

        $this->time += microtime(TRUE);
        return $html;
    }

}
