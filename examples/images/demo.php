<?php

/**
 * TEXY! USER IMAGES DEMO
 * --------------------------------------
 *
 * This demo shows how Texy! control images (useful for CMS)
 *     - programmable images controlling
 *     - onMouseOver state
 *     - support for preloading
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 */



// include Texy!
require_once dirname(__FILE__).'/../../texy/texy.php';



class myHandler {

    function image($texy, &$image, &$link)
    {
        if ($image->imageURL != 'user')  // accept only [* user *]
          return FALSE;

        $image->imageURL = 'image.gif'; // image URL
        $image->overURL = 'image-over.gif'; // onmouseover image
        $image->modifier->title = 'Texy! logo';

        if ($link) $link->URL = 'image-big.gif'; // linked image
    }

}


$texy = new Texy();
$texy->handler = new myHandler;
$texy->imageModule->root       = 'imagesdir/';       // "in-line" images root
$texy->imageModule->linkedRoot = 'imagesdir/big/';   // "linked" images root
$texy->imageModule->leftClass  = 'my-left-class';    // left-floated image modifier
$texy->imageModule->rightClass = 'my-right-class';   // right-floated image modifier
$texy->imageModule->defaultAlt = 'default alt. text';// default image alternative text


// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);  // that's all folks!


// echo formated output
header('Content-type: text/html; charset=utf-8');
echo $html;



// echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';



// echo all used images
echo '<hr />';
echo '<pre>';
echo 'used images:';
print_r($texy->summary['images']);
echo 'onmouseover images:';
print_r($texy->summary['preload']);
echo '</pre>';




// build preload script!
$script = "var preloadImg = new Array();\n";
foreach ($texy->summary['preload'] as $key => $image)
    $script .= "preloadImg[$key] = new Image(); preloadImg[$key].src='".htmlSpecialChars($image, ENT_QUOTES)."';\n";

echo '<pre>';
echo $script;
echo '</pre>';
