<?php

/**
 * TEXY! USER SYNTAX DEMO
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



$texy = new Texy();

// disable *** and ** and * phrases
$texy->allowed['phraseStrongEm'] = FALSE;
$texy->allowed['phraseStrong'] = FALSE;
$texy->allowed['phraseEm'] = FALSE;

$texy->allowed['mySyntax1'] = TRUE;
$texy->allowed['mySyntax2'] = TRUE;


// add new syntax: *bold*
$texy->registerLinePattern(
    'userHandler',
    '#(?<!\*)\*(?!\ |\*)(.+)'.TEXY_MODIFIER.'?(?<!\ |\*)\*(?!\*)()#U',
    'mySyntax1'
);

// add new syntax: _italic_
$texy->registerLinePattern(
    'userHandler',
    '#(?<!_)_(?!\ |_)(.+)'.TEXY_MODIFIER.'?(?<!\ |_)_(?!_)()#U',
    'mySyntax2'
);


function userHandler($parser, $matches, $name)
{
    list($match, $mContent, $mMod1, $mMod2, $mMod3) = $matches;

    global $texy;

    // create element
    $tag = $name === 'mySyntax1' ? 'b' : 'i';
    $el = TexyHtml::el($tag);

    // apply modifier
    $mod = new TexyModifier;
    $mod->setProperties($mMod1, $mMod2, $mMod3);
    $mod->decorate($texy, $el);

    $el->class = 'myclass';
    $el->setContent($mContent);

    // parse inner content of this element
    $parser->again = TRUE;

    return $el;
}


// processing
$text = file_get_contents('syntax.texy');
$html = $texy->process($text);  // that's all folks!

// echo formated output
header('Content-type: text/html; charset=utf-8');
echo $html;

// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';

?>