<?php

/**
 * TEXY! FIGURE DEMO
 * -----------------
 *
 * This demo shows how change default figures behaviour
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
     * @param TexyBlockParser
     * @param TexyImage
     * @param TexyLink
     * @param string
     * @param TexyModifier
     * @return TexyHtml|string|FALSE|Texy::PROCEED
     */
    public function figure($parser, $image, $link, $content, $modifier)
    {
        // finish invocation by default way
        $el = $parser->texy->figureModule->solve($image, $link, $content, $modifier);

        // change div -> dl
        $el->setName('dl');

        // change p -> dd
        $el->childNodes['caption']->setName('dd');

        // wrap img into dt
        $img = $el->childNodes['img'];
        $el->childNodes['img'] = TexyHtml::el('dt')->addChild($img);

        return $el;
    }

}


$texy = new Texy();
$texy->handler = new myHandler;

// optionally set CSS classes
/*
$texy->figureModule->class = 'figure';
$texy->figureModule->leftClass = 'figure-left';
$texy->figureModule->rightClass = 'figure-right';
*/

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
