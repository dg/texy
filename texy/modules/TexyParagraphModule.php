<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004-2007 David Grudl aka -dgx- (http://www.dgx.cz)
 * @license    GNU GENERAL PUBLIC LICENSE version 2
 * @version    $Revision$ $Date$
 * @category   Text
 * @package    Texy
 */

// security - include texy.php, not this file
if (!class_exists('Texy', FALSE)) die();



/**
 * Paragraph module
 */
class TexyParagraphModule extends TexyModule
{
    /** @var bool  how split paragraphs (internal usage) */
    public $mode;



    public function begin()
    {
        $this->mode = TRUE;
    }



    /**
     * Finish invocation
     *
     * @param string
     * @param TexyModifier
     * @return TexyHtml|FALSE
     */
    public function solve($content, $mod)
    {
        $tx = $this->texy;

        // find hard linebreaks
        if ($tx->mergeLines) {
            // ....
            //  ...  => \r means break line
            $content = preg_replace('#\n (?=\S)#', "\r", $content);
        } else {
            $content = preg_replace('#\n#', "\r", $content);
        }

        $el = TexyHtml::el('p');
        $el->parseLine($tx, $content);
        $content = $el->getText(); // string

        // check content type
        // block contains block tag
        if (strpos($content, Texy::CONTENT_BLOCK) !== FALSE) {
            $el->setName(NULL);  // ignores modifier!

        // block contains text (protected)
        } elseif (strpos($content, Texy::CONTENT_TEXTUAL) !== FALSE) {
            // leave element p

        // block contains text
        } elseif (preg_match('#[^\s'.TEXY_MARK.']#u', $content)) {
            // leave element p

        // block contains only replaced element
        } elseif (strpos($content, Texy::CONTENT_REPLACED) !== FALSE) {
            $el->setName('div');

        // block contains only markup tags or spaces or nothig
        } else {
            if ($tx->ignoreEmptyStuff) return FALSE;
            if ($mod->empty) $el->setName(NULL);
        }

        if ($el->getName()) {
            // apply modifier
            if ($mod) $mod->decorate($tx, $el);

            // add <br />
            if (strpos($content, "\r") !== FALSE) {
                $key = $tx->protect('<br />', Texy::CONTENT_REPLACED);
                $content = str_replace("\r", $key, $content);
            };
        }

        $content = strtr($content, "\r\n", '  ');
        $el->setText($content);

        return $el;
    }


}
