<?php

/**
 * TEXY! USER HANDLER DEMO
 * -----------------------
 *
 * @author   David Grudl aka -dgx- (http://www.dgx.cz)
 * @version  $Revision$ $Date$
 */



// include Texy!
require_once dirname(__FILE__).'/../../texy/texy.php';



$texy = new Texy();
$texy->handler = new myHandler;


class myHandler
{


    /** Line parsing */

    /**
     * @param TexyLineParser
     * @param string
     * @param string
     * @return TexyHtml|string|FALSE|Texy::PROCEED
     */
    function emoticon($parser, $emoticon, $rawEmoticon)
    {
        // return $parser->texy->emoticonModule->solve($emoticon, $rawEmoticon);
    }

    /**
     * @param TexyLineParser
     * @param TexyImage
     * @param TexyLink
     * @return TexyHtml|string|FALSE|Texy::PROCEED
     */
    function image($parser, $image, $link)
    {
        // return $parser->texy->imageModule->solve($image, $link);
    }

    /**
     * @param TexyLineParser
     * @param TexyLink
     * @param string
     * @return TexyHtml|string|FALSE|Texy::PROCEED
     */
    function linkReference($parser, $link, $content)
    {
        // return $parser->texy->linkModule->solve($link, $content);
    }

    /**
     * @param TexyLineParser
     * @param TexyLink
     * @param string
     * @return TexyHtml|string|FALSE|Texy::PROCEED
     */
    function linkEmail($parser, $link, $content)
    {
        // return $parser->texy->linkModule->solve($link, $content);
    }

    /**
     * @param TexyLineParser
     * @param TexyLink
     * @param string
     * @return TexyHtml|string|FALSE|Texy::PROCEED
     */
    function linkURL($parser, $link, $content)
    {
        // return $parser->texy->linkModule->solve($link, $content);
    }

    /**
     * @param TexyLineParser
     * @param string
     * @param string
     * @param TexyModifier
     * @param TexyLink
     * @return TexyHtml|string|FALSE|Texy::PROCEED
     */
    function phrase($parser, $phrase, $content, $modifier, $link)
    {
        // return $parser->texy->phraseModule->solve($phrase, $content, $modifier, $link);
    }

    /**
     * @param TexyLineParser
     * @param string
     * @return TexyHtml|string|FALSE|Texy::PROCEED
     */
    function newReference($parser, $name)
    {
        // return Texy::PROCEED;
        // return $parser->texy->linkModule->solve($link, $content);
    }

    /**
     * @param TexyLineParser
     * @param string
     * @return TexyHtml|string|FALSE|Texy::PROCEED
     */
    function htmlComment($parser, $content)
    {
        // return Texy::PROCEED;
        // return $parser->texy->htmlModule->solveComment($content);
    }

    /**
     * @param TexyLineParser
     * @param TexyHtml
     * @param bool
     * @param bool
     * @return TexyHtml|string|FALSE|Texy::PROCEED
     */
    function htmlTag($parser, $el, $isStart, $forceEmpty=NULL)
    {
        // return Texy::PROCEED;
        // return $parser->texy->htmlModule->solveTag($el, $isStart, $forceEmpty);
    }

    /**
     * @param TexyLineParser
     * @param string  command
     * @param array   arguments
     * @param string  arguments in raw format
     * @return TexyHtml|string|FALSE|Texy::PROCEED
     */
    function script($parser, $cmd, $args, $raw)
    {
        // return Texy::PROCEED;
    }



    /** Blocks */

    /**
     * @param TexyBlockParser
     * @param string
     * @param TexyModifier
     * @return TexyHtml|string|FALSE|Texy::PROCEED
     */
    function paragraph($parser, $content, $modifier)
    {
        // return $parser->texy->paragraphModule->solve($content, $modifier);
    }

    /**
     * @param TexyBlockParser
     * @param TexyImage
     * @param TexyLink
     * @param string
     * @param TexyModifier
     * @return TexyHtml|string|FALSE|Texy::PROCEED
     */
    function figure($parser, $image, $link, $content, $modifier)
    {
        // return $parser->texy->figureModule->solve($image, $link, $content, $modifier);
    }

    /**
     * @param TexyBlockParser
     * @param int
     * @param string
     * @param TexyModifier
     * @param bool
     * @return TexyHtml|string|FALSE|Texy::PROCEED
     */
    function heading($parser, $level, $content, $modifier, $isSurrounded)
    {
        // return $parser->texy->headingModule->solve($level, $content, $modifier, $isSurrounded);
    }

    /**
     * @param TexyBlockParser
     * @param string
     * @param string
     * @param string
     * @param TexyModifier
     * @return TexyHtml|string|Texy::PROCEED
     */
    function block($parser, $blocktype, $content, $param, $modifier)
    {
        // return $parser->texy->blockModule->solve($blocktype, $content, $param, $modifier);
    }

    /**
     * @param TexyBlockParser
     * @param TexyHtml
     * @param TexyModifier
     * @return void
     */
    function afterList($parser, $element, $modifier)
    {
    }

    /**
     * @param TexyBlockParser
     * @param TexyHtml
     * @param TexyModifier
     * @return void
     */
    function afterDefinitionList($parser, $element, $modifier)
    {
    }

    /**
     * @param TexyBlockParser
     * @param TexyHtml
     * @param TexyModifier
     * @return void
     */
    function afterTable($parser, $element, $modifier)
    {
    }

    /**
     * @param TexyBlockParser
     * @param TexyHtml
     * @param TexyModifier
     * @return void
     */
    function afterBlockquote($parser, $element, $modifier)
    {
    }

    /**
     * @param TexyBlockParser
     * @param TexyHtml
     * @param TexyModifier
     * @return void
     */
    function afterHorizline($parser, $element, $modifier)
    {
    }



    /** Special */

    /**
     * @param Texy
     * @param TexyHtml
     * @param bool
     * @return void
     */
    function afterParse($texy, $DOM, $isSingleLine)
    {
    }
}
