<?php

/**
 * TEXY! FIGURE DEMO
 * -----------------
 *
 * This demo shows how change default figures behaviour
 *
 * @author   David Grudl aka -dgx- (http://www.dgx.cz)
 * @version  $Revision$ $Date$
 */



// include Texy!
require_once dirname(__FILE__).'/../../texy/texy.php';



/**
 * @param TexyHandlerInvocation  handler invocation
 * @param TexyImage
 * @param TexyLink
 * @param string
 * @param TexyModifier
 * @return TexyHtml|string|FALSE
 */
function figureHandler($invocation, $image, $link, $content, $modifier)
{
    // finish invocation by default way
    $el = $invocation->proceed();

    // change div -> dl
    $el->setName('dl');

    // change p -> dd
    $el->children['caption']->setName('dd');

    // wrap img into dt
    $dt = TexyHtml::el('dt');
    $dt->add($el->children['img']);
    $el->children['img'] = $dt;

    return $el;
}


$texy = new Texy();
$texy->addHandler('figure', 'figureHandler');

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
