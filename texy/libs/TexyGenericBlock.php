<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    $Revision$ $Date$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();



/**
 * Generic block module
 */
class TexyGenericBlock
{
    /** @var Texy */
    public $texy;


    public function __construct($texy)
    {
        $this->texy = $texy;
    }


    /**
     * Callback function for blocks
     *
     *  ....  .(title)[class]{style}>
     *   ...
     *   ...
     *
     */
    public function process($parser, $content)
    {
        $tx = $this->texy;
        if ($tx->_paragraphMode)
            $parts = preg_split('#(\n{2,})#', $content);
        else
            $parts = preg_split('#(\n(?! )|\n{2,})#', $content);

        foreach ($parts as $content) {
            $content = trim($content);
            if ($content === '') continue;

            preg_match('#^(.*)'.TEXY_MODIFIER_H.'?(\n.*)?()$#sU', $content, $matches);
            list(, $mContent, $mMod, $mContent2) = $matches;


            // ....
            //  ...  => \n
            $mContent = trim($mContent . $mContent2);
            if ($tx->mergeLines) {
                // \r means break line
                $mContent = preg_replace('#\n (?=\S)#', "\r", $mContent);
            }

            $lineParser = new TexyLineParser($tx);
            $content = $lineParser->parse($mContent);

            // check content type
            $contentType = Texy::CONTENT_NONE;
            if (strpos($content, Texy::CONTENT_BLOCK) !== FALSE) {
                $contentType = Texy::CONTENT_BLOCK;
            } elseif (strpos($content, Texy::CONTENT_TEXTUAL) !== FALSE) {
                $contentType = Texy::CONTENT_TEXTUAL;
            } else {
                if (strpos($content, Texy::CONTENT_INLINE) !== FALSE) $contentType = Texy::CONTENT_INLINE;
                $s = trim( preg_replace('#['.TEXY_MARK.']+#', '', $content) );
                if (strlen($s)) $contentType = Texy::CONTENT_TEXTUAL;
            }

            // specify tag
            if ($contentType === Texy::CONTENT_TEXTUAL) $tag = 'p';
            elseif ($mMod) $tag = 'div';
            elseif ($contentType === Texy::CONTENT_BLOCK) $tag = '';
            else $tag = 'div';

            // add <br />
            if ($tag && (strpos($content, "\r") !== FALSE)) {
                $key = $tx->protect('<br />', Texy::CONTENT_INLINE);
                $content = str_replace("\r", $key, $content);
            };
            $content = strtr($content, "\r\n", '  ');

            $mod = new TexyModifier($mMod);
            $el = TexyHtml::el($tag);
            $mod->decorate($tx, $el);
            $el->childNodes[] = $content;


            $parser->children[] = $el;
        }
    }

} // TexyGenericBlock
