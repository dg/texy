<?php

/**
 * TEXY! SUPPORT FOR FLASH
 * --------------------------------------
 *
 * @author   David Grudl aka -dgx- (http://www.dgx.cz)
 * @version  $Revision$ $Date$
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
        $parts = explode(':', $image->URL);
        if (count($parts) !== 2) return Texy::PROCEED;

        switch ($parts[0]) {
        case 'youtube':
            $video = htmlSpecialChars($parts[1]);
            $dimensions = 'width="'.($image->width ? $image->width : 425).'" height="'.($image->height ? $image->height : 350).'"';
            $code = '<object '.$dimensions.'>'
                . '<param name="movie" value="http://www.youtube.com/v/'.$video.'" /><param name="wmode" value="transparent" />'
                . '<embed src="http://www.youtube.com/v/'.$video.'" type="application/x-shockwave-flash" wmode="transparent" '.$dimensions.' /></object>';

            return $parser->texy->protect($code, Texy::CONTENT_BLOCK);
        }

        return Texy::PROCEED;
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
