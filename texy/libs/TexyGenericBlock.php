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
        if ($this->texy->_mergeMode)
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
            if ($this->texy->mergeLines) {
               $mContent = preg_replace('#\n (\S)#', " \r\\1", $mContent);
               $mContent = strtr($mContent, "\n\r", " \n");
            }

            $el = new TexyParagraphElement($this->texy);
            $el->parse($mContent);

            // check content type
            $contentType = Texy::CONTENT_NONE;
            if (strpos($el->content, "\x17") !== FALSE) {
                $contentType = Texy::CONTENT_BLOCK;
            } elseif (strpos($el->content, "\x16") !== FALSE) {
                $contentType = Texy::CONTENT_TEXTUAL;
            } else {
                if (strpos($el->content, "\x15") !== FALSE) $contentType = Texy::CONTENT_INLINE;
                $s = trim( preg_replace('#['.TEXY_MARK.']+#', '', $el->content) );
                if (strlen($s)) $contentType = Texy::CONTENT_TEXTUAL;
            }

            // specify tag
            if ($contentType === Texy::CONTENT_TEXTUAL) $tag = 'p';
            elseif ($mMod1 || $mMod2 || $mMod3 || $mMod4) $tag = 'div';
            elseif ($contentType === Texy::CONTENT_BLOCK) $tag = '';
            else $tag = 'div';

            // add <br />
            if ($tag && (strpos($el->content, "\n") !== FALSE)) {
                $key = $this->texy->mark('<br />', Texy::CONTENT_INLINE);
                $el->content = strtr($el->content, array("\n" => $key));
            }

            if ($mMod1 || $mMod2 || $mMod3 || $mMod4) {
                $mod = new TexyModifier($this->texy);
                $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
                $el->tags[0] = $mod->generate($tag);
            } else {
                $el->tags[0] = TexyHtml::el($tag);
            }

            $parser->children[] = $el;
        }
    }

} // TexyGenericBlock
