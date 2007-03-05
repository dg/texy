<?php

/**
 * TEXY! THIRD PARTY SYNTAX HIGHLIGHTING
 * --------------------------------------
 *
 * This demo shows how combine Texy! with syntax highlighter FSHL
 *       - define user callback (for /--code elements)
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 */


// include libs
require_once dirname(__FILE__).'/../../texy/texy.php';

$fshlPath = dirname(__FILE__).'/fshl/';
include_once $fshlPath . 'fshl.php';


if (!class_exists('fshlParser'))
    die('DOWNLOAD <a href="http://hvge.sk/scripts/fshl/">FSHL</a> AND UNPACK TO FSHL FOLDER FIRST!');



// this is user callback object for processing Texy events
class myHandler
{

    /**
     * User handler for code block
     *
     * @param TexyDocumentParser
     * @param string  text to highlight
     * @param string  language
     * @param TexyModifier modifier
     * @return TexyHtml
     */
    function documentCode($parser, $content, $lang, $modifier)
    {
        $texy = $parser->texy;

        $lang = strtoupper($lang);
        if ($lang == 'JAVASCRIPT') $lang = 'JS';
        if (!in_array(
                $lang,
                array('CPP', 'CSS', 'HTML', 'JAVA', 'PHP', 'JS', 'SQL'))
           ) return NULL;

        $parser = new fshlParser('HTML_UTF8', P_TAB_INDENT);

        $content = $texy->documentModule->outdent($content);
        $content = $parser->highlightString($lang, $content);
        $content = $texy->protect($content);

        $el = TexyHtml::el('code');
        $el->setContent( $content );

        $elPre = TexyHtml::el('pre');
        if ($modifier) $modifier->decorate($texy, $elPre);
        $elPre->class = strtolower($lang);
        $elPre->addChild($el);

        return $elPre;
    }

}



$texy = new Texy();
$texy->handler = new myHandler;

// processing
$text = file_get_contents('sample.texy');
$html = $texy->process($text);  // that's all folks!

// echo Geshi Stylesheet
header('Content-type: text/html; charset=utf-8');
echo '<style type="text/css">'. file_get_contents($fshlPath.'styles/COHEN_style.css') . '</style>';
echo '<title>' . $texy->headingModule->title . '</title>';
// echo formated output
echo $html;

// and echo generated HTML code
echo '<hr />';
echo '<pre>';
echo htmlSpecialChars($html);
echo '</pre>';
