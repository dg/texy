<?php

/**
 * TEXY! SUPPORT FOR FLASH
 * --------------------------------------
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


    /**
     * User handler for images
     *
     * @param TexyLineParser
     * @param TexyImage
     * @param TexyLink
     * @return TexyHtml|string|FALSE|Texy::PROCEED
     */
    function image($parser, $image, $link)
    {
        $texy = $parser->texy;

        if (substr($image->URL, -4) === '.swf')  // accepts only *.swf
        {
            $movie = Texy::absolutize($image->URL, $texy->imageModule->root);

            $dimensions = 
                   ($image->width ? 'width="'.$image->width.'" ' : '')
                . ($image->height ? 'width="'.$image->height.'" ' : '');

            $movie = htmlSpecialChars($movie);
            $altContent = htmlSpecialChars($image->modifier->title);

            // @see http://www.dgx.cz/trine/item/how-to-correctly-insert-a-flash-into-xhtml
            $code = '
<!--[if !IE]> -->
<object type="application/x-shockwave-flash" data="'.$movie.'" '.$dimensions.'>
<!-- <![endif]-->

<!--[if IE]>
<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" '.$dimensions.'
codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=5,0,0,0">
<param name="movie" value="'.$movie.'" />
<!--><!--dgx-->

    <p>'.$altContent.'</p>
</object>
<!-- <![endif]-->
';
            return $texy->protect($code, Texy::CONTENT_BLOCK);
        }

        return Texy::PROCEED;
        // or return $texy->imageModule->solve($image, $link);
    }

}


$texy = new Texy();
$texy->handler = new myHandler;


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


