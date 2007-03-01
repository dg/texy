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
            list(, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mContent2) = $matches;
            //    [1] => ...
            //    [2] => (title)
            //    [3] => [class]
            //    [4] => {style}
            //    [5] => >


            // ....
            //  ...  => \n
            $mContent = trim($mContent . $mContent2);
            if ($tx->mergeLines) {
               $mContent = preg_replace('#\n (\S)#', " \r\\1", $mContent);
               $mContent = strtr($mContent, "\n\r", " \n");
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
            elseif ($mMod1 || $mMod2 || $mMod3 || $mMod4) $tag = 'div';
            elseif ($contentType === Texy::CONTENT_BLOCK) $tag = '';
            else $tag = 'div';

            // add <br />
            if ($tag && (strpos($content, "\n") !== FALSE)) {
                $key = $tx->protect('<br />', Texy::CONTENT_INLINE);
                $content = strtr($content, array("\n" => $key));
            } else {
                $content = strtr($content, array("\n" => ' '));
            }

            $mod = new TexyModifier;
            $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
            $el = TexyHtml::el($tag);
            $mod->decorate($tx, $el);
            $el->childNodes[] = $content;


            $parser->children[] = $el;
        }
    }

} // TexyGenericBlock
