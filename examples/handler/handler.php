<?php

/**
 * TEXY! USER HANDLER DEMO
 * -----------------------
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
$texy->handler = new myHandler;


class myHandler
{


    /** Line parsing */

    /**
     * @param TexyLineParser
     * @param string
     * @param string
     * @param string
     * @return TexyHtml|string|FALSE|NULL
     */
    public function emoticon($parser, $emoticon, $rawEmoticon, $file)
    {
        // return $parser->texy->emoticonModule->solve($emoticon, $rawEmoticon, $file);
    }

    /**
     * @param TexyLineParser
     * @param TexyImage
     * @param TexyLink
     * @return TexyHtml|string|FALSE|NULL
     */
    public function image($parser, $image, $link)
    {
        // return $parser->texy->imageModule->solve($image, $link);
    }

    /**
     * @param TexyLineParser
     * @param TexyLink
     * @param string
     * @return TexyHtml|string|FALSE|NULL
     */
    public function linkReference($parser, $link, $content)
    {
        // return $parser->texy->linkModule->solve($link, $content);
    }

    /**
     * @param TexyLineParser
     * @param TexyLink
     * @param string
     * @return TexyHtml|string|FALSE|NULL
     */
    public function linkEmail($parser, $link, $content)
    {
        // return $parser->texy->linkModule->solve($link, $content);
    }

    /**
     * @param TexyLineParser
     * @param TexyLink
     * @param string
     * @return TexyHtml|string|FALSE|NULL
     */
    public function linkURL($parser, $link, $content)
    {
        // return $parser->texy->linkModule->solve($link, $content);
    }

    /**
     * @param TexyLineParser
     * @param string
     * @param string
     * @param TexyModifier
     * @param TexyLink
     * @return TexyHtml|string|FALSE|NULL
     */
    public function phrase($parser, $phrase, $content, $modifier, $link)
    {
        // return $parser->texy->phraseModule->solve($phrase, $content, $modifier, $link);
    }

    /**
     * @param TexyLineParser
     * @param string
     * @return TexyHtml|string|FALSE|NULL
     */
    public function newReference($parser, $name)
    {
        // return NULL;
    }

    /**
     * @param TexyLineParser
     * @param string
     * @return TexyHtml|string|FALSE|NULL
     */
    public function htmlComment($parser, $match)
    {
        // return NULL;
    }

    /**
     * @param TexyLineParser
     * @param string
     * @return TexyHtml|string|FALSE|NULL
     */
    public function htmlTag($parser, $el, $isOpening)
    {
        // return NULL;
    }





    /** Blocks */

    /**
     * @param TexyBlockParser
     * @param TexyImage
     * @param TexyLink
     * @param string
     * @param TexyModifier
     * @return TexyHtml|string|FALSE|NULL
     */
    public function figure($parser, $image, $link, $content, $modifier)
    {
        // return $parser->texy->figureModule->solve($image, $link, $content, $modifier);
    }

    /**
     * @param TexyBlockParser
     * @param int
     * @param string
     * @param TexyModifier
     * @param bool
     * @return TexyHtml|string|FALSE|NULL
     */
    public function heading($parser, $level, $content, $modifier, $isSurrounded)
    {
        // return $parser->texy->headingModule->solve($level, $content, $modifier, $isSurrounded);
    }

    /**
     * @param TexyBlockParser
     * @param string
     * @param string
     * @param string
     * @param TexyModifier
     * @return TexyHtml|string|NULL
     */
    public function block($parser, $blocktype, $content, $param, $modifier)
    {
        // return $parser->texy->blockModule->solve($blocktype, $content, $param, $modifier);
    }

    /**
     * @param TexyBlockParser
     * @param TexyHtml
     * @param TexyModifier
     * @return void
     */
    public function afterList($parser, $element, $modifier)
    {
    }

    /**
     * @param TexyBlockParser
     * @param TexyHtml
     * @param TexyModifier
     * @return void
     */
    public function afterDefinitionList($parser, $element, $modifier)
    {
    }

    /**
     * @param TexyBlockParser
     * @param TexyHtml
     * @param TexyModifier
     * @return void
     */
    public function afterTable($parser, $element, $modifier)
    {
    }

    /**
     * @param TexyBlockParser
     * @param TexyHtml
     * @param TexyModifier
     * @return void
     */
    public function afterBlockquote($parser, $element, $modifier)
    {
    }

    /**
     * @param TexyBlockParser
     * @param TexyHtml
     * @param TexyModifier
     * @return void
     */
    public function afterHorizline($parser, $element, $modifier)
    {
    }



    /** Special */

    /**
     * @param Texy
     * @param TexyHtml
     * @param bool
     * @return void
     */
    public function afterParse($texy, $DOM, $isSingleLine)
    {
    }
}
